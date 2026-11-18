<?php
require_once 'config.php';

$loggedIn  = isset($_SESSION['user_id']);
$userId    = $loggedIn ? (int)$_SESSION['user_id'] : 0;
$listingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ─── Fetch listing ────────────────────────────────────────────────────────────
$listing = null;
if ($listingId > 0) {
    $stmt = $pdo->prepare("
        SELECT listings.*, users.name AS seller_name, users.email AS seller_email
        FROM listings
        JOIN users ON listings.user_id = users.id
        WHERE listings.id = :id
    ");
    $stmt->execute([':id' => $listingId]);
    $listing = $stmt->fetch();
}

// ─── Handle POST actions ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $listing) {
    $action = $_POST['action'] ?? '';

    // Make an offer
    if ($action === 'make_offer' && $loggedIn && $listing['user_id'] != $userId) {
        $amount  = isset($_POST['amount'])  ? (float)$_POST['amount']  : 0;
        $message = isset($_POST['message']) ? trim($_POST['message'])   : '';

        if ($amount > 0) {
            $ins = $pdo->prepare("
                INSERT INTO offers (listing_id, buyer_id, amount, message)
                VALUES (:listing_id, :buyer_id, :amount, :message)
            ");
            $ins->execute([
                ':listing_id' => $listingId,
                ':buyer_id'   => $userId,
                ':amount'     => $amount,
                ':message'    => $message,
            ]);
        }
        header("Location: listing.php?id=$listingId&success=1");
        exit;
    }

    // Seller actions on offers
    if ($action === 'accept_offer' && $loggedIn && $listing['user_id'] == $userId) {
        $offerId = (int)($_POST['offer_id'] ?? 0);
        $pdo->prepare("UPDATE offers SET status='accepted', updated_at=NOW() WHERE id=? AND listing_id=?")
            ->execute([$offerId, $listingId]);
        $pdo->prepare("UPDATE listings SET status='sold', updated_at=NOW() WHERE id=?")
            ->execute([$listingId]);
        header("Location: listing.php?id=$listingId&tab=offers");
        exit;
    }

    if ($action === 'reject_offer' && $loggedIn && $listing['user_id'] == $userId) {
        $offerId = (int)($_POST['offer_id'] ?? 0);
        $pdo->prepare("UPDATE offers SET status='rejected', updated_at=NOW() WHERE id=? AND listing_id=?")
            ->execute([$offerId, $listingId]);
        header("Location: listing.php?id=$listingId&tab=offers");
        exit;
    }

    if ($action === 'counter_offer' && $loggedIn && $listing['user_id'] == $userId) {
        $offerId       = (int)($_POST['offer_id'] ?? 0);
        $counterAmount = (float)($_POST['counter_amount'] ?? 0);
        if ($counterAmount > 0) {
            $pdo->prepare("UPDATE offers SET status='countered', counter_amount=?, updated_at=NOW() WHERE id=? AND listing_id=?")
                ->execute([$counterAmount, $offerId, $listingId]);
        }
        header("Location: listing.php?id=$listingId&tab=offers");
        exit;
    }
}

// Re-fetch listing after possible status change
if ($listingId > 0) {
    $stmt = $pdo->prepare("
        SELECT listings.*, users.name AS seller_name, users.email AS seller_email
        FROM listings
        JOIN users ON listings.user_id = users.id
        WHERE listings.id = :id
    ");
    $stmt->execute([':id' => $listingId]);
    $listing = $stmt->fetch();
}

$isSeller = $listing && $loggedIn && $listing['user_id'] == $userId;

// ─── Fetch offers ─────────────────────────────────────────────────────────────
$sellerOffers = [];
$myOffer      = null;

if ($listing) {
    if ($isSeller) {
        $oStmt = $pdo->prepare("
            SELECT offers.*, users.name AS buyer_name
            FROM offers
            JOIN users ON offers.buyer_id = users.id
            WHERE offers.listing_id = :lid
            ORDER BY offers.created_at DESC
        ");
        $oStmt->execute([':lid' => $listingId]);
        $sellerOffers = $oStmt->fetchAll();
    } elseif ($loggedIn) {
        $oStmt = $pdo->prepare("
            SELECT * FROM offers
            WHERE listing_id = :lid AND buyer_id = :bid
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $oStmt->execute([':lid' => $listingId, ':bid' => $userId]);
        $myOffer = $oStmt->fetch();
    }
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
$conditionLabels = [
    'new'      => 'New',
    'like_new' => 'Like New',
    'good'     => 'Good',
    'fair'     => 'Fair',
    'poor'     => 'Poor',
];
$conditionColors = [
    'new'      => 'bg-green-100 text-green-700',
    'like_new' => 'bg-blue-100 text-blue-700',
    'good'     => 'bg-gray-100 text-gray-600',
    'fair'     => 'bg-yellow-100 text-yellow-700',
    'poor'     => 'bg-red-100 text-red-600',
];
$categoryLabels = [
    'electronics' => 'Electronics',
    'furniture'   => 'Furniture',
    'kitchen'     => 'Kitchen',
    'fashion'     => 'Fashion',
    'other'       => 'Other',
];
$categoryEmojis = [
    'electronics' => '💻',
    'furniture'   => '🪑',
    'kitchen'     => '🍳',
    'fashion'     => '👗',
    'other'       => '📦',
];
$offerStatusColors = [
    'pending'   => 'bg-yellow-100 text-yellow-700',
    'accepted'  => 'bg-green-100 text-green-700',
    'rejected'  => 'bg-red-100 text-red-600',
    'countered' => 'bg-blue-100 text-blue-700',
];

$success = !empty($_GET['success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title><?= $listing ? htmlspecialchars($listing['title']) . ' – CloseRate' : 'Listing Not Found – CloseRate' ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  body { font-family: system-ui, -apple-system, "Segoe UI", sans-serif; }
</style>
</head>
<body class="bg-[#F6F5F2] min-h-screen flex flex-col">

<!-- ===== HEADER ===== -->
<header class="border-b border-[#8B8181]">
	<nav class="max-w-6xl mx-auto px-6 py-6 flex items-center gap-6">
		<a href="home.php" class="text-4xl font-bold">CloseRate</a>
		<div class="flex-1"></div>

		<div class="hidden md:flex items-center gap-10 text-[22px]">
			<a href="home.php" class="text-[#595959] hover:text-black">Home</a>
			<a href="listings.php" class="text-[#4368E5] font-medium border-b-2 border-[#4368E5] pb-1">Listings</a>
			<a href="dashboard.php" class="text-black hover:text-[#4368E5]">Dashboard</a>
		</div>

		<?php if ($isLoggedIn): ?>
    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
        <a href="admin-dashboard.php" class="ml-4 text-base text-[#4368E5] font-medium hover:opacity-80">Admin</a>
    <?php endif; ?>
    <a href="logout.php" class="ml-4 text-base text-[#595959] hover:text-black">Log out</a>
<?php else: ?>
			<div class="flex items-center gap-3 ml-4 text-base">
				<a href="index.php" class="text-[#595959] hover:text-black">Sign in</a>
				<a href="register.php" class="bg-[#4368E5] text-white px-4 py-2 rounded-[14px] hover:opacity-90">Sign up</a>
			</div>
		<?php endif; ?>
	</nav>

	<div class="md:hidden flex justify-center gap-8 pb-4 text-lg">
		<a href="home.php" class="text-[#595959]">Home</a>
		<a href="listings.php" class="text-[#4368E5] font-medium">Listings</a>
		<a href="dashboard.php">Dashboard</a>
	</div>
</header>

<!-- ===== MAIN ===== -->
<main class="flex-1">
  <div class="max-w-6xl mx-auto px-6 py-10">

    <?php if (!$listing): ?>
    <!-- ── NOT FOUND ─────────────────────────────────────────────────────── -->
    <div class="bg-white rounded-[30px] border border-[#BFBABA] p-16 text-center">
      <div class="text-7xl mb-4">🔍</div>
      <h2 class="text-2xl font-bold text-gray-800 mb-2">Listing Not Found</h2>
      <p class="text-[#8B8181] mb-6">This listing may have been removed or never existed.</p>
      <a href="listings.php" class="bg-[#4368E5] text-white px-6 py-2 rounded-full text-sm font-medium hover:opacity-90">
        ← Back to Listings
      </a>
    </div>

    <?php else:
      $cat       = $listing['category'] ?? 'other';
      $emoji     = $categoryEmojis[$cat] ?? '📦';
      $cond      = $listing['condition_type'] ?? 'good';
      $condColor = $conditionColors[$cond]  ?? 'bg-gray-100 text-gray-600';
      $condLabel = $conditionLabels[$cond]  ?? ucfirst($cond);
      $catLabel  = $categoryLabels[$cat]    ?? ucfirst($cat);
      $price     = (float)$listing['price'];
      $priceLow  = $price * 0.85;
      $priceHigh = $price * 1.15;
      $priceMid  = $price;
    ?>

    <!-- ── SUCCESS BANNER ────────────────────────────────────────────────── -->
    <?php if ($success): ?>
    <div class="bg-green-50 border border-green-200 text-green-700 rounded-[14px] px-5 py-3 mb-6 flex items-center gap-2">
      ✅ <span>Your offer was submitted successfully!</span>
    </div>
    <?php endif; ?>

    <!-- Back link -->
    <a href="listings.php" class="inline-flex items-center gap-1 text-[#595959] hover:text-[#4368E5] text-sm mb-6">
      ← Back to Listings
    </a>

    <!-- ── 2-COL LAYOUT ──────────────────────────────────────────────────── -->
    <div class="grid md:grid-cols-2 gap-8">

      <!-- LEFT: Image -->
      <div>
        <?php if (!empty($listing['image_url'])): ?>
          <img src="<?= htmlspecialchars($listing['image_url']) ?>"
               alt="<?= htmlspecialchars($listing['title']) ?>"
               class="w-full rounded-[20px] object-cover max-h-96" />
        <?php else: ?>
          <div class="w-full h-80 bg-white rounded-[20px] border border-[#BFBABA] flex items-center justify-center text-8xl">
            <?= $emoji ?>
          </div>
        <?php endif; ?>

        <!-- Fair Offer Meter -->
        <div class="bg-white rounded-[20px] border border-[#BFBABA] p-6 mt-5">
          <h3 class="font-semibold text-gray-800 mb-1">Fair Offer Meter</h3>
          <p class="text-[#8B8181] text-xs mb-4">Estimated fair price range based on this listing's asking price ±15%</p>
          <div class="relative h-3 bg-[#F6F5F2] rounded-full overflow-hidden">
            <div class="absolute inset-0 rounded-full"
                 style="background: linear-gradient(to right, #f87171, #facc15, #4ade80)"></div>
            <!-- Needle at centre (asking price) -->
            <div class="absolute top-1/2 -translate-y-1/2 w-3 h-3 bg-[#4368E5] rounded-full border-2 border-white shadow"
                 style="left: 50%; transform: translate(-50%, -50%)"></div>
          </div>
          <div class="flex justify-between text-xs text-[#8B8181] mt-2">
            <span>$<?= number_format($priceLow, 0) ?></span>
            <span class="text-[#4368E5] font-semibold">$<?= number_format($priceMid, 2) ?> asking</span>
            <span>$<?= number_format($priceHigh, 0) ?></span>
          </div>
        </div>
      </div>

      <!-- RIGHT: Details -->
      <div class="flex flex-col gap-5">
        <div class="bg-white rounded-[30px] border border-[#BFBABA] p-7">
          <!-- Status badge if sold/removed -->
          <?php if ($listing['status'] !== 'active'): ?>
          <div class="mb-3">
            <span class="bg-blue-100 text-blue-700 text-xs px-3 py-1 rounded-full font-medium uppercase tracking-wide">
              <?= htmlspecialchars(ucfirst($listing['status'])) ?>
            </span>
          </div>
          <?php endif; ?>

          <h1 class="text-2xl font-bold text-gray-900 mb-3"><?= htmlspecialchars($listing['title']) ?></h1>

          <!-- Price -->
          <p class="text-[#4368E5] text-3xl font-bold mb-4">$<?= number_format($price, 2) ?></p>

          <!-- Badges -->
          <div class="flex gap-2 flex-wrap mb-4">
            <span class="bg-[#4368E5]/10 text-[#4368E5] text-xs px-3 py-1 rounded-full font-medium">
              <?= htmlspecialchars($catLabel) ?>
            </span>
            <span class="<?= $condColor ?> text-xs px-3 py-1 rounded-full font-medium">
              <?= htmlspecialchars($condLabel) ?>
            </span>
          </div>

          <!-- Description -->
          <?php if (!empty($listing['description'])): ?>
          <div class="mb-4">
            <h4 class="text-sm font-semibold text-gray-700 mb-1">Description</h4>
            <p class="text-[#595959] text-sm leading-relaxed whitespace-pre-line">
              <?= htmlspecialchars($listing['description']) ?>
            </p>
          </div>
          <?php endif; ?>

          <!-- Meta -->
          <div class="border-t border-[#BFBABA] pt-4 flex flex-col gap-2 text-sm text-[#595959]">
            <div class="flex justify-between">
              <span class="text-[#8B8181]">Seller</span>
              <span class="font-medium"><?= htmlspecialchars($listing['seller_name']) ?></span>
            </div>
            <div class="flex justify-between">
              <span class="text-[#8B8181]">Listed</span>
              <span><?= date('M j, Y', strtotime($listing['created_at'])) ?></span>
            </div>
          </div>

          <!-- Seller CTA -->
          <?php if ($isSeller): ?>
          <a href="sell.php?edit=<?= $listingId ?>"
             class="mt-5 inline-block bg-[#4368E5] text-white text-sm py-2 px-5 rounded-[14px] hover:opacity-90">
            ✏️ Edit Listing
          </a>
          <?php elseif ($loggedIn && $listing['status'] === 'active' && !$myOffer): ?>
          <!-- Make an offer form -->
          <div class="mt-5">
            <h4 class="font-semibold text-gray-800 mb-3">Make an Offer</h4>
            <form method="POST" action="listing.php?id=<?= $listingId ?>" class="flex flex-col gap-3">
              <input type="hidden" name="action" value="make_offer" />
              <div>
                <label class="text-xs text-[#8B8181] mb-1 block">Your Offer Amount ($)</label>
                <input
                  type="number"
                  name="amount"
                  min="0.01"
                  step="0.01"
                  placeholder="<?= number_format($price, 2) ?>"
                  required
                  class="w-full border border-[#BFBABA] rounded-[14px] px-4 py-2 text-sm focus:outline-none focus:border-[#4368E5]"
                />
              </div>
              <div>
                <label class="text-xs text-[#8B8181] mb-1 block">Message to Seller (optional)</label>
                <textarea
                  name="message"
                  rows="3"
                  placeholder="Hi, I'm interested in this item…"
                  class="w-full border border-[#BFBABA] rounded-[14px] px-4 py-2 text-sm focus:outline-none focus:border-[#4368E5] resize-none"
                ></textarea>
              </div>
              <button type="submit"
                      class="bg-[#4368E5] text-white py-2 px-5 rounded-[14px] text-sm font-medium hover:opacity-90">
                Submit Offer
              </button>
            </form>
          </div>

          <?php elseif ($loggedIn && $myOffer): ?>
          <!-- Buyer sees their offer status -->
          <div class="mt-5 bg-[#F6F5F2] rounded-[14px] p-4">
            <h4 class="font-semibold text-gray-800 mb-2">Your Offer</h4>
            <div class="flex items-center justify-between mb-1">
              <span class="text-[#595959] text-sm">Amount</span>
              <span class="font-bold text-[#4368E5]">$<?= number_format((float)$myOffer['amount'], 2) ?></span>
            </div>
            <div class="flex items-center justify-between mb-1">
              <span class="text-[#595959] text-sm">Status</span>
              <span class="<?= $offerStatusColors[$myOffer['status']] ?? 'bg-gray-100 text-gray-600' ?> text-xs px-3 py-1 rounded-full font-medium">
                <?= ucfirst($myOffer['status']) ?>
              </span>
            </div>
            <?php if ($myOffer['status'] === 'countered' && $myOffer['counter_amount']): ?>
            <div class="flex items-center justify-between mt-1">
              <span class="text-[#595959] text-sm">Counter Offer</span>
              <span class="font-bold text-[#4368E5]">$<?= number_format((float)$myOffer['counter_amount'], 2) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($myOffer['message']): ?>
            <p class="text-xs text-[#8B8181] mt-2 italic">"<?= htmlspecialchars($myOffer['message']) ?>"</p>
            <?php endif; ?>
          </div>

          <?php elseif (!$loggedIn && $listing['status'] === 'active'): ?>
          <div class="mt-5 p-4 bg-[#F6F5F2] rounded-[14px] text-center">
            <p class="text-[#595959] text-sm mb-3">Sign in to make an offer</p>
            <a href="index.php" class="bg-[#4368E5] text-white px-5 py-2 rounded-full text-sm font-medium hover:opacity-90">
              Sign in
            </a>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div><!-- end 2-col -->

    <!-- ── SELLER: OFFERS RECEIVED ──────────────────────────────────────── -->
    <?php if ($isSeller && !empty($sellerOffers)): ?>
    <div class="mt-10">
      <h2 class="text-xl font-bold text-gray-900 mb-5">Offers Received (<?= count($sellerOffers) ?>)</h2>
      <div class="flex flex-col gap-4">
        <?php foreach ($sellerOffers as $offer):
          $oStatusColor = $offerStatusColors[$offer['status']] ?? 'bg-gray-100 text-gray-600';
        ?>
        <div class="bg-white rounded-[20px] border border-[#BFBABA] p-6">
          <div class="flex flex-wrap items-start justify-between gap-4 mb-3">
            <div>
              <p class="font-semibold text-gray-800"><?= htmlspecialchars($offer['buyer_name']) ?></p>
              <p class="text-[#8B8181] text-xs"><?= date('M j, Y g:i A', strtotime($offer['created_at'])) ?></p>
            </div>
            <div class="text-right">
              <p class="text-[#4368E5] text-xl font-bold">$<?= number_format((float)$offer['amount'], 2) ?></p>
              <span class="<?= $oStatusColor ?> text-xs px-2 py-1 rounded-full font-medium">
                <?= ucfirst($offer['status']) ?>
              </span>
            </div>
          </div>

          <?php if ($offer['message']): ?>
          <p class="text-[#595959] text-sm italic mb-3">"<?= htmlspecialchars($offer['message']) ?>"</p>
          <?php endif; ?>

          <?php if ($offer['status'] === 'countered' && $offer['counter_amount']): ?>
          <p class="text-sm text-[#595959] mb-3">
            Your counter: <span class="font-bold text-[#4368E5]">$<?= number_format((float)$offer['counter_amount'], 2) ?></span>
          </p>
          <?php endif; ?>

          <!-- Action buttons (only for pending offers) -->
          <?php if ($offer['status'] === 'pending' && $listing['status'] === 'active'): ?>
          <div class="flex flex-wrap gap-3 mt-3">
            <!-- Accept -->
            <form method="POST" action="listing.php?id=<?= $listingId ?>">
              <input type="hidden" name="action"   value="accept_offer" />
              <input type="hidden" name="offer_id" value="<?= (int)$offer['id'] ?>" />
              <button class="bg-green-500 text-white px-4 py-2 rounded-[14px] text-sm font-medium hover:opacity-90">
                ✅ Accept
              </button>
            </form>

            <!-- Reject -->
            <form method="POST" action="listing.php?id=<?= $listingId ?>">
              <input type="hidden" name="action"   value="reject_offer" />
              <input type="hidden" name="offer_id" value="<?= (int)$offer['id'] ?>" />
              <button class="bg-red-500 text-white px-4 py-2 rounded-[14px] text-sm font-medium hover:opacity-90"
                      onclick="return confirm('Reject this offer?')">
                ❌ Reject
              </button>
            </form>

            <!-- Counter -->
            <form method="POST" action="listing.php?id=<?= $listingId ?>" class="flex gap-2 items-center">
              <input type="hidden" name="action"   value="counter_offer" />
              <input type="hidden" name="offer_id" value="<?= (int)$offer['id'] ?>" />
              <input
                type="number"
                name="counter_amount"
                min="0.01"
                step="0.01"
                placeholder="Counter amount"
                required
                class="border border-[#BFBABA] rounded-[14px] px-3 py-2 text-sm w-36 focus:outline-none focus:border-[#4368E5]"
              />
              <button class="bg-[#4368E5] text-white px-4 py-2 rounded-[14px] text-sm font-medium hover:opacity-90">
                ↩️ Counter
              </button>
            </form>
          </div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <?php elseif ($isSeller && empty($sellerOffers)): ?>
    <div class="mt-10 bg-white rounded-[20px] border border-[#BFBABA] p-10 text-center">
      <div class="text-5xl mb-3">📭</div>
      <p class="text-[#8B8181]">No offers yet. Share your listing to attract buyers!</p>
    </div>
    <?php endif; ?>

    <?php endif; // end listing exists ?>

  </div>
</main>

<!-- ===== FOOTER ===== -->
<footer class="border-t-[3px] border-[#8B8181] bg-white mt-10">
  <div class="max-w-6xl mx-auto px-6 py-8 flex flex-col sm:flex-row items-center justify-between gap-4">
    <span class="font-bold text-2xl text-[#4368E5]">CloseRate</span>
    <p class="text-[#8B8181] text-sm">&copy; <?= date('Y') ?> CloseRate. All rights reserved.</p>
    <div class="flex gap-5 text-sm text-[#595959]">
      <a href="listings.php"  class="hover:text-[#4368E5]">Listings</a>
      <a href="dashboard.php" class="hover:text-[#4368E5]">Dashboard</a>
    </div>
  </div>
</footer>

</body>
</html>
