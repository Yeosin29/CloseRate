<?php
require_once 'auth-check.php';
require_once 'config.php';

$userId   = $_SESSION['user_id'];
$userName = $_SESSION['name'] ?? 'User';

// Active tab
$tab = $_GET['tab'] ?? 'listings';
if (!in_array($tab, ['listings', 'made', 'received'])) $tab = 'listings';

// ── Fetch user's listings ─────────────────────────────────────────────────────
$stmtListings = $pdo->prepare("SELECT * FROM listings WHERE user_id = ? ORDER BY created_at DESC");
$stmtListings->execute([$userId]);
$myListings = $stmtListings->fetchAll();

// ── Fetch offers MADE by this user (as buyer) ─────────────────────────────────
$stmtMade = $pdo->prepare("
    SELECT o.*, l.title AS listing_title, l.price AS listing_price, l.id AS listing_id
    FROM offers o
    JOIN listings l ON o.listing_id = l.id
    WHERE o.buyer_id = ?
    ORDER BY o.created_at DESC
");
$stmtMade->execute([$userId]);
$offersMade = $stmtMade->fetchAll();

// ── Fetch offers RECEIVED on user's listings (as seller) ──────────────────────
$stmtReceived = $pdo->prepare("
    SELECT o.*, l.title AS listing_title, l.id AS listing_id, u.name AS buyer_name
    FROM offers o
    JOIN listings l ON o.listing_id = l.id
    JOIN users u ON o.buyer_id = u.id
    WHERE l.user_id = ?
    ORDER BY o.created_at DESC
");
$stmtReceived->execute([$userId]);
$offersReceived = $stmtReceived->fetchAll();

// ── Stats ─────────────────────────────────────────────────────────────────────
$totalListings   = count($myListings);
$activeListings  = count(array_filter($myListings, fn($l) => $l['status'] === 'active'));
$soldListings    = count(array_filter($myListings, fn($l) => $l['status'] === 'sold'));
$totalReceived   = count($offersReceived);
$pendingReceived = count(array_filter($offersReceived, fn($o) => $o['status'] === 'pending'));

// ── Helpers ───────────────────────────────────────────────────────────────────
function statusBadge(string $status): string {
    $map = [
        'active'    => 'bg-green-100 text-green-700',
        'sold'      => 'bg-blue-100 text-blue-700',
        'removed'   => 'bg-gray-100 text-gray-500',
        'pending'   => 'bg-yellow-100 text-yellow-700',
        'accepted'  => 'bg-green-100 text-green-700',
        'rejected'  => 'bg-red-100 text-red-500',
        'countered' => 'bg-purple-100 text-purple-700',
    ];
    $cls = $map[$status] ?? 'bg-gray-100 text-gray-500';
    return "<span class=\"inline-block text-xs px-2 py-1 rounded-full font-medium {$cls}\">" . htmlspecialchars(ucfirst(str_replace('_', ' ', $status))) . "</span>";
}

function conditionLabel(string $c): string {
    $map = ['new'=>'New','like_new'=>'Like New','good'=>'Good','fair'=>'Fair','poor'=>'Poor'];
    return $map[$c] ?? ucfirst($c);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — CloseRate</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: system-ui, -apple-system, "Segoe UI", sans-serif; }
    </style>
</head>
<body class="bg-[#F6F5F2] min-h-screen flex flex-col">

<!-- Header -->
<header class="border-b border-[#8B8181]">
	<nav class="max-w-6xl mx-auto px-6 py-6 flex items-center gap-6">
		<a href="home.php" class="text-4xl font-bold">CloseRate</a>
		<div class="flex-1"></div>

		<div class="hidden md:flex items-center gap-10 text-[22px]">
			<a href="home.php" class="text-[#595959] hover:text-black">Home</a>
			<a href="listings.php" class="text-[#595959] hover:text-black">Listings</a>
			<a href="dashboard.php" class="text-[#4368E5] font-medium border-b-2 border-[#4368E5] pb-1">Dashboard</a>
		</div>

		<?php if (isset($_SESSION['user_id'])): ?>
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
		<a href="listings.php" class="text-[#595959]">Listings</a>
		<a href="dashboard.php" class="text-[#4368E5] font-medium">Dashboard</a>
	</div>
</header>

<!-- Main -->
<main class="flex-1 bg-[#F6F5F2]">
    <div class="max-w-5xl mx-auto px-6 py-10">

        <!-- Welcome -->
        <h1 class="text-[28px] font-bold text-black mb-8">Welcome back, <?= htmlspecialchars($userName) ?></h1>

        <!-- Stats row -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-10">
            <div class="bg-white rounded-[20px] border border-[#BFBABA] p-5 text-center">
                <div class="text-3xl font-bold text-[#4368E5]"><?= $totalListings ?></div>
                <div class="text-[#8B8181] text-sm mt-1">Total Listings</div>
            </div>
            <div class="bg-white rounded-[20px] border border-[#BFBABA] p-5 text-center">
                <div class="text-3xl font-bold text-[#4368E5]"><?= $activeListings ?></div>
                <div class="text-[#8B8181] text-sm mt-1">Active Listings</div>
            </div>
            <div class="bg-white rounded-[20px] border border-[#BFBABA] p-5 text-center">
                <div class="text-3xl font-bold text-[#4368E5]"><?= $soldListings ?></div>
                <div class="text-[#8B8181] text-sm mt-1">Sold Listings</div>
            </div>
            <div class="bg-white rounded-[20px] border border-[#BFBABA] p-5 text-center">
                <div class="text-3xl font-bold text-[#4368E5]"><?= $totalReceived ?></div>
                <div class="text-[#8B8181] text-sm mt-1">Offers Received</div>
            </div>
        </div>

        <!-- Extra stat: pending offers -->
        <?php if ($pendingReceived > 0): ?>
        <div class="mb-8 bg-yellow-50 border border-yellow-200 rounded-[14px] px-5 py-3 text-yellow-700 text-sm">
            You have <strong><?= $pendingReceived ?></strong> pending offer<?= $pendingReceived !== 1 ? 's' : '' ?> awaiting your response.
        </div>
        <?php endif; ?>

        <!-- Tab navigation -->
        <div class="flex gap-3 mb-6 flex-wrap">
            <a href="?tab=listings"
               class="<?= $tab === 'listings' ? 'bg-[#4368E5] text-white' : 'bg-white border border-[#BFBABA] text-[#595959]' ?> px-5 py-2 rounded-full text-sm font-medium hover:opacity-90 transition-opacity">
                My Listings
            </a>
            <a href="?tab=made"
               class="<?= $tab === 'made' ? 'bg-[#4368E5] text-white' : 'bg-white border border-[#BFBABA] text-[#595959]' ?> px-5 py-2 rounded-full text-sm font-medium hover:opacity-90 transition-opacity">
                Offers Made
            </a>
            <a href="?tab=received"
               class="<?= $tab === 'received' ? 'bg-[#4368E5] text-white' : 'bg-white border border-[#BFBABA] text-[#595959]' ?> px-5 py-2 rounded-full text-sm font-medium hover:opacity-90 transition-opacity">
                Offers Received
                <?php if ($pendingReceived > 0): ?>
                    <span class="ml-1 bg-yellow-400 text-white text-xs rounded-full px-1.5 py-0.5"><?= $pendingReceived ?></span>
                <?php endif; ?>
            </a>

            <?php if ($tab === 'listings'): ?>
            <a href="sell.php" class="ml-auto bg-[#4368E5] text-white px-5 py-2 rounded-full text-sm font-medium hover:opacity-90 transition-opacity">
                + List a new item
            </a>
            <?php endif; ?>
        </div>

        <!-- ══════════════════ MY LISTINGS TAB ══════════════════ -->
        <?php if ($tab === 'listings'): ?>
        <div class="bg-white rounded-[20px] border border-[#BFBABA] overflow-hidden">
            <?php if (empty($myListings)): ?>
                <div class="p-10 text-center text-[#8B8181]">
                    <p class="text-lg mb-4">You haven't listed anything yet.</p>
                    <a href="sell.php" class="bg-[#4368E5] text-white py-2 px-6 rounded-full text-sm hover:opacity-90">List your first item</a>
                </div>
            <?php else: ?>
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-[#4368E5] text-white text-sm">
                            <th class="px-4 py-3 text-left">Title</th>
                            <th class="px-4 py-3 text-left">Price</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-left">Condition</th>
                            <th class="px-4 py-3 text-left">Listed</th>
                            <th class="px-4 py-3 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($myListings as $l): ?>
                        <tr class="border-b border-[#BFBABA] hover:bg-[#F6F5F2] transition-colors">
                            <td class="px-4 py-3 text-sm font-medium text-black max-w-[200px] truncate">
                                <?= htmlspecialchars($l['title']) ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-[#595959]">$<?= number_format((float)$l['price'], 2) ?></td>
                            <td class="px-4 py-3"><?= statusBadge($l['status']) ?></td>
                            <td class="px-4 py-3 text-sm text-[#595959]"><?= conditionLabel($l['condition_type']) ?></td>
                            <td class="px-4 py-3 text-sm text-[#8B8181]"><?= date('M j, Y', strtotime($l['created_at'])) ?></td>
                            <td class="px-4 py-3">
                                <div class="flex gap-2">
                                    <a href="sell.php?edit=<?= (int)$l['id'] ?>"
                                       class="bg-[#4368E5] text-white text-xs px-3 py-1.5 rounded-[10px] hover:opacity-90 font-medium">Edit</a>
                                    <a href="listing.php?id=<?= (int)$l['id'] ?>"
                                       class="bg-white border border-[#BFBABA] text-[#595959] text-xs px-3 py-1.5 rounded-[10px] hover:bg-[#F6F5F2] font-medium">View</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- ══════════════════ OFFERS MADE TAB ══════════════════ -->
        <?php elseif ($tab === 'made'): ?>
        <div class="bg-white rounded-[20px] border border-[#BFBABA] overflow-hidden">
            <?php if (empty($offersMade)): ?>
                <div class="p-10 text-center text-[#8B8181]">
                    <p class="text-lg">You haven't made any offers yet.</p>
                    <a href="listings.php" class="inline-block mt-4 bg-[#4368E5] text-white py-2 px-6 rounded-full text-sm hover:opacity-90">Browse Listings</a>
                </div>
            <?php else: ?>
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-[#4368E5] text-white text-sm">
                            <th class="px-4 py-3 text-left">Listing</th>
                            <th class="px-4 py-3 text-left">List Price</th>
                            <th class="px-4 py-3 text-left">Your Offer</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-left">Counter</th>
                            <th class="px-4 py-3 text-left">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($offersMade as $o): ?>
                        <tr class="border-b border-[#BFBABA] hover:bg-[#F6F5F2] transition-colors">
                            <td class="px-4 py-3 text-sm font-medium max-w-[200px] truncate">
                                <a href="listing.php?id=<?= (int)$o['listing_id'] ?>" class="text-[#4368E5] hover:underline">
                                    <?= htmlspecialchars($o['listing_title']) ?>
                                </a>
                            </td>
                            <td class="px-4 py-3 text-sm text-[#595959]">$<?= number_format((float)$o['listing_price'], 2) ?></td>
                            <td class="px-4 py-3 text-sm font-semibold text-black">$<?= number_format((float)$o['amount'], 2) ?></td>
                            <td class="px-4 py-3"><?= statusBadge($o['status']) ?></td>
                            <td class="px-4 py-3 text-sm text-[#595959]">
                                <?= $o['counter_amount'] ? '$' . number_format((float)$o['counter_amount'], 2) : '—' ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-[#8B8181]"><?= date('M j, Y', strtotime($o['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- ══════════════════ OFFERS RECEIVED TAB ══════════════════ -->
        <?php elseif ($tab === 'received'): ?>
        <div class="bg-white rounded-[20px] border border-[#BFBABA] overflow-hidden">
            <?php if (empty($offersReceived)): ?>
                <div class="p-10 text-center text-[#8B8181]">
                    <p class="text-lg">No offers received yet.</p>
                </div>
            <?php else: ?>
                <div class="divide-y divide-[#BFBABA]">
                    <?php foreach ($offersReceived as $o): ?>
                    <div class="p-5 hover:bg-[#F6F5F2] transition-colors">
                        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-3 mb-1 flex-wrap">
                                    <a href="listing.php?id=<?= (int)$o['listing_id'] ?>" class="font-semibold text-[#4368E5] hover:underline text-sm truncate max-w-[260px]">
                                        <?= htmlspecialchars($o['listing_title']) ?>
                                    </a>
                                    <?= statusBadge($o['status']) ?>
                                </div>
                                <p class="text-sm text-[#595959]">
                                    <span class="font-medium text-black"><?= htmlspecialchars($o['buyer_name']) ?></span>
                                    offered <span class="font-semibold text-black">$<?= number_format((float)$o['amount'], 2) ?></span>
                                    <?php if ($o['counter_amount']): ?>
                                        &mdash; counter: <span class="font-semibold text-[#4368E5]">$<?= number_format((float)$o['counter_amount'], 2) ?></span>
                                    <?php endif; ?>
                                </p>
                                <?php if ($o['message']): ?>
                                    <p class="mt-1 text-xs text-[#8B8181] italic">"<?= htmlspecialchars($o['message']) ?>"</p>
                                <?php endif; ?>
                                <p class="text-xs text-[#8B8181] mt-1"><?= date('M j, Y g:i a', strtotime($o['created_at'])) ?></p>
                            </div>

                            <?php if ($o['status'] === 'pending'): ?>
                            <div class="flex gap-2 flex-wrap shrink-0">
                                <!-- Accept -->
                                <form method="POST" action="listing.php">
                                    <input type="hidden" name="action" value="accept_offer">
                                    <input type="hidden" name="offer_id" value="<?= (int)$o['id'] ?>">
                                    <input type="hidden" name="listing_id" value="<?= (int)$o['listing_id'] ?>">
                                    <button type="submit"
                                        class="bg-green-500 text-white text-xs px-4 py-2 rounded-[10px] hover:opacity-90 font-medium">
                                        Accept
                                    </button>
                                </form>
                                <!-- Reject -->
                                <form method="POST" action="listing.php" onsubmit="return confirm('Reject this offer?');">
                                    <input type="hidden" name="action" value="reject_offer">
                                    <input type="hidden" name="offer_id" value="<?= (int)$o['id'] ?>">
                                    <input type="hidden" name="listing_id" value="<?= (int)$o['listing_id'] ?>">
                                    <button type="submit"
                                        class="bg-white border border-red-400 text-red-500 text-xs px-4 py-2 rounded-[10px] hover:bg-red-50 font-medium">
                                        Reject
                                    </button>
                                </form>
                                <!-- Counter -->
                                <button type="button"
                                    onclick="document.getElementById('counter-form-<?= (int)$o['id'] ?>').classList.toggle('hidden')"
                                    class="bg-white border border-[#4368E5] text-[#4368E5] text-xs px-4 py-2 rounded-[10px] hover:bg-blue-50 font-medium">
                                    Counter
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($o['status'] === 'pending'): ?>
                        <!-- Counter form (hidden by default) -->
                        <div id="counter-form-<?= (int)$o['id'] ?>" class="hidden mt-4">
                            <form method="POST" action="listing.php" class="flex items-center gap-3 flex-wrap">
                                <input type="hidden" name="action" value="counter_offer">
                                <input type="hidden" name="offer_id" value="<?= (int)$o['id'] ?>">
                                <input type="hidden" name="listing_id" value="<?= (int)$o['listing_id'] ?>">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm text-[#595959]">Counter amount: $</span>
                                    <input type="number" name="counter_amount" step="0.01" min="0" required
                                        placeholder="0.00"
                                        class="border border-[#BFBABA] rounded-[10px] px-3 py-2 text-sm text-black outline-none focus:border-[#4368E5] w-28">
                                </div>
                                <button type="submit"
                                    class="bg-[#4368E5] text-white text-xs px-4 py-2 rounded-[10px] hover:opacity-90 font-medium">
                                    Send Counter
                                </button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>
</main>

<!-- Footer -->
<footer class="border-t-[3px] border-[#8B8181] bg-white mt-auto">
    <div class="max-w-6xl mx-auto px-6 py-8 flex flex-col md:flex-row items-center justify-between gap-4">
        <span class="font-bold text-xl text-black">CloseRate</span>
        <p class="text-[#8B8181] text-sm">&copy; <?= date('Y') ?> CloseRate. All rights reserved.</p>
        <div class="flex gap-5 text-sm text-[#595959]">
            <a href="home.php" class="hover:text-black">Home</a>
            <a href="listings.php" class="hover:text-black">Listings</a>
            <a href="dashboard.php" class="hover:text-black">Dashboard</a>
        </div>
    </div>
</footer>

</body>
</html>
