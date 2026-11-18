<?php
require_once 'config.php';
// Admin guard
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

// Handle POST actions BEFORE HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $tab    = $_POST['tab'] ?? 'users';

    if ($action === 'delete_user') {
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($uid && $uid !== (int)$_SESSION['user_id']) {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND id != ?");
            $stmt->execute([$uid, (int)$_SESSION['user_id']]);
        }
        header("Location: admin-dashboard.php?tab=users");
        exit;
    }

    if ($action === 'toggle_role') {
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($uid) {
            $stmt = $pdo->prepare("UPDATE users SET role = IF(role='admin','user','admin') WHERE id = ?");
            $stmt->execute([$uid]);
        }
        header("Location: admin-dashboard.php?tab=users");
        exit;
    }

    if ($action === 'delete_listing') {
        $lid = (int)($_POST['listing_id'] ?? 0);
        if ($lid) {
            $stmt = $pdo->prepare("DELETE FROM listings WHERE id = ?");
            $stmt->execute([$lid]);
        }
        header("Location: admin-dashboard.php?tab=listings");
        exit;
    }

    if ($action === 'toggle_listing') {
        $lid = (int)($_POST['listing_id'] ?? 0);
        if ($lid) {
            $stmt = $pdo->prepare("UPDATE listings SET status = IF(status='active','removed','active') WHERE id = ?");
            $stmt->execute([$lid]);
        }
        header("Location: admin-dashboard.php?tab=listings");
        exit;
    }

    // Fallback redirect
    header("Location: admin-dashboard.php?tab=" . urlencode($tab));
    exit;
}

// Active tab
$tab = $_GET['tab'] ?? 'users';
if (!in_array($tab, ['users', 'listings', 'offers'])) {
    $tab = 'users';
}

// Fetch all users
$users = $pdo->query(
    "SELECT id, name, email, role, created_at,
            (SELECT COUNT(*) FROM listings WHERE user_id = users.id) AS listing_count
     FROM users
     ORDER BY created_at DESC"
)->fetchAll();

// Fetch all listings
$listings = $pdo->query(
    "SELECT listings.*, users.name AS seller_name,
            (SELECT COUNT(*) FROM offers WHERE listing_id = listings.id) AS offer_count
     FROM listings
     JOIN users ON listings.user_id = users.id
     ORDER BY listings.created_at DESC"
)->fetchAll();

// Fetch all offers
$offers = $pdo->query(
    "SELECT offers.*, listings.title AS listing_title, listings.price AS listing_price,
            u1.name AS buyer_name, u2.name AS seller_name
     FROM offers
     JOIN listings ON offers.listing_id = listings.id
     JOIN users u1 ON offers.buyer_id = u1.id
     JOIN users u2 ON listings.user_id = u2.id
     ORDER BY offers.created_at DESC"
)->fetchAll();

// Stats
$totalUsers    = count($users);
$totalListings = count($listings);
$activeListings = count(array_filter($listings, fn($l) => $l['status'] === 'active'));
$totalOffers   = count($offers);
$pendingOffers = count(array_filter($offers, fn($o) => $o['status'] === 'pending'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard – CloseRate</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: system-ui, -apple-system, "Segoe UI", sans-serif; }
    </style>
</head>
<body class="bg-[#F6F5F2] min-h-screen flex flex-col">

<!-- HEADER -->
<header class="border-b border-[#8B8181]">
	<nav class="max-w-6xl mx-auto px-6 py-6 flex items-center gap-6">
		<a href="home.php" class="text-4xl font-bold">CloseRate</a>
		<div class="flex-1"></div>

		<div class="hidden md:flex items-center gap-10 text-[22px]">
			<a href="home.php" class="text-[#595959] hover:text-black">Home</a>
			<a href="listings.php" class="text-[#595959] hover:text-black">Listings</a>
			<a href="dashboard.php" class="text-[#595959] hover:text-black">Dashboard</a>
		</div>

		<a href="admin-dashboard.php" class="ml-4 text-base text-[#4368E5] font-medium hover:opacity-80">Admin</a>
		<a href="logout.php" class="ml-4 text-base text-[#595959] hover:text-black">Log out</a>
	</nav>

	<div class="md:hidden flex justify-center gap-8 pb-4 text-lg">
		<a href="home.php" class="text-[#595959]">Home</a>
		<a href="listings.php" class="text-[#595959]">Listings</a>
		<a href="dashboard.php" class="text-[#595959]">Dashboard</a>
		<a href="admin-dashboard.php" class="text-[#4368E5] font-medium">Admin</a>
	</div>
</header>

<!-- MAIN -->
<main class="bg-[#F6F5F2] flex-1">
    <div class="max-w-6xl mx-auto px-6 py-10">

        <h1 class="text-[28px] font-bold text-gray-900 mb-2">Admin Dashboard</h1>
        <p class="text-[#8B8181] mb-8">Manage users, listings, and offers.</p>

        <!-- STATS ROW -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-10">
            <?php
            $stats = [
                ['label' => 'Total Users',    'value' => $totalUsers],
                ['label' => 'Total Listings', 'value' => $totalListings],
                ['label' => 'Active Listings','value' => $activeListings],
                ['label' => 'Total Offers',   'value' => $totalOffers],
            ];
            foreach ($stats as $s): ?>
            <div class="bg-white rounded-[20px] border border-[#BFBABA] px-6 py-5">
                <div class="text-[28px] font-bold text-gray-900"><?= $s['value'] ?></div>
                <div class="text-sm text-[#8B8181] mt-1"><?= $s['label'] ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- TABS -->
        <div class="flex gap-2 mb-6 border-b border-[#BFBABA]">
            <?php
            $tabs = ['users' => 'Users', 'listings' => 'Listings', 'offers' => 'Offers'];
            foreach ($tabs as $key => $label):
                $active = $tab === $key;
            ?>
            <a href="?tab=<?= $key ?>"
               class="px-5 py-3 text-sm font-semibold transition-colors
                      <?= $active
                            ? 'text-[#4368E5] border-b-2 border-[#4368E5] -mb-px'
                            : 'text-[#595959] hover:text-[#4368E5]' ?>">
                <?= $label ?>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- ================================================================
             USERS TAB
        ================================================================ -->
        <?php if ($tab === 'users'): ?>
        <div class="overflow-x-auto">
            <table class="w-full border-collapse bg-white rounded-[20px] border border-[#BFBABA] overflow-hidden">
                <thead class="bg-[#4368E5] text-white text-sm">
                    <tr>
                        <th class="px-4 py-3 text-left">ID</th>
                        <th class="px-4 py-3 text-left">Name</th>
                        <th class="px-4 py-3 text-left">Email</th>
                        <th class="px-4 py-3 text-left">Role</th>
                        <th class="px-4 py-3 text-left">Listings</th>
                        <th class="px-4 py-3 text-left">Joined</th>
                        <th class="px-4 py-3 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u):
                        $isSelf = (int)$u['id'] === (int)$_SESSION['user_id'];
                    ?>
                    <tr class="border-b border-[#BFBABA] hover:bg-[#F6F5F2]">
                        <td class="px-4 py-3 text-sm text-[#595959]"><?= $u['id'] ?></td>
                        <td class="px-4 py-3 text-sm font-medium text-gray-900"><?= htmlspecialchars($u['name']) ?></td>
                        <td class="px-4 py-3 text-sm text-[#595959]"><?= htmlspecialchars($u['email']) ?></td>
                        <td class="px-4 py-3 text-sm">
                            <?php if ($u['role'] === 'admin'): ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-[#4368E5]/10 text-[#4368E5]">admin</span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">user</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-[#595959]"><?= $u['listing_count'] ?></td>
                        <td class="px-4 py-3 text-sm text-[#595959]"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                        <td class="px-4 py-3 text-sm">
                            <div class="flex items-center gap-2 flex-wrap">
                                <!-- Toggle Role -->
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="toggle_role">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <input type="hidden" name="tab" value="users">
                                    <button type="submit"
                                            class="rounded-[10px] px-3 py-1 text-xs font-medium bg-[#4368E5]/10 text-[#4368E5] hover:bg-[#4368E5]/20 transition-colors">
                                        <?= $u['role'] === 'admin' ? 'Make User' : 'Make Admin' ?>
                                    </button>
                                </form>
                                <!-- Delete -->
                                <?php if (!$isSelf): ?>
                                <form method="POST" class="inline"
                                      onsubmit="return confirm('Delete user <?= htmlspecialchars(addslashes($u['name'])) ?>? This cannot be undone.');">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <input type="hidden" name="tab" value="users">
                                    <button type="submit"
                                            class="rounded-[10px] px-3 py-1 text-xs font-medium bg-red-100 text-red-600 hover:bg-red-200 transition-colors">
                                        Delete
                                    </button>
                                </form>
                                <?php else: ?>
                                <span class="text-xs text-[#8B8181] italic">You</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-[#8B8181] text-sm">No users found.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ================================================================
             LISTINGS TAB
        ================================================================ -->
        <?php elseif ($tab === 'listings'): ?>
        <div class="overflow-x-auto">
            <table class="w-full border-collapse bg-white rounded-[20px] border border-[#BFBABA] overflow-hidden">
                <thead class="bg-[#4368E5] text-white text-sm">
                    <tr>
                        <th class="px-4 py-3 text-left">ID</th>
                        <th class="px-4 py-3 text-left">Title</th>
                        <th class="px-4 py-3 text-left">Seller</th>
                        <th class="px-4 py-3 text-left">Category</th>
                        <th class="px-4 py-3 text-left">Price</th>
                        <th class="px-4 py-3 text-left">Condition</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-left">Offers</th>
                        <th class="px-4 py-3 text-left">Date</th>
                        <th class="px-4 py-3 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($listings as $l): ?>
                    <tr class="border-b border-[#BFBABA] hover:bg-[#F6F5F2]">
                        <td class="px-4 py-3 text-sm text-[#595959]"><?= $l['id'] ?></td>
                        <td class="px-4 py-3 text-sm font-medium text-gray-900 max-w-[180px] truncate">
                            <a href="listing.php?id=<?= $l['id'] ?>" class="hover:text-[#4368E5] transition-colors">
                                <?= htmlspecialchars($l['title']) ?>
                            </a>
                        </td>
                        <td class="px-4 py-3 text-sm text-[#595959]"><?= htmlspecialchars($l['seller_name']) ?></td>
                        <td class="px-4 py-3 text-sm text-[#595959] capitalize"><?= htmlspecialchars($l['category'] ?? 'other') ?></td>
                        <td class="px-4 py-3 text-sm font-semibold text-gray-900">$<?= number_format($l['price'], 2) ?></td>
                        <td class="px-4 py-3 text-sm text-[#595959] capitalize"><?= str_replace('_', ' ', $l['condition_type'] ?? 'good') ?></td>
                        <td class="px-4 py-3 text-sm">
                            <?php
                            $statusClasses = [
                                'active'  => 'bg-green-100 text-green-700',
                                'sold'    => 'bg-blue-100 text-blue-700',
                                'removed' => 'bg-gray-100 text-gray-500',
                            ];
                            $sc = $statusClasses[$l['status']] ?? 'bg-gray-100 text-gray-500';
                            ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold <?= $sc ?>">
                                <?= ucfirst($l['status']) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-[#595959]"><?= $l['offer_count'] ?></td>
                        <td class="px-4 py-3 text-sm text-[#595959]"><?= date('M j, Y', strtotime($l['created_at'])) ?></td>
                        <td class="px-4 py-3 text-sm">
                            <div class="flex items-center gap-2 flex-wrap">
                                <!-- Toggle Status -->
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="toggle_listing">
                                    <input type="hidden" name="listing_id" value="<?= $l['id'] ?>">
                                    <input type="hidden" name="tab" value="listings">
                                    <button type="submit"
                                            class="rounded-[10px] px-3 py-1 text-xs font-medium bg-yellow-100 text-yellow-700 hover:bg-yellow-200 transition-colors">
                                        <?= $l['status'] === 'active' ? 'Remove' : 'Activate' ?>
                                    </button>
                                </form>
                                <!-- Delete -->
                                <form method="POST" class="inline"
                                      onsubmit="return confirm('Delete listing \"<?= htmlspecialchars(addslashes($l['title'])) ?>\"? This cannot be undone.');">
                                    <input type="hidden" name="action" value="delete_listing">
                                    <input type="hidden" name="listing_id" value="<?= $l['id'] ?>">
                                    <input type="hidden" name="tab" value="listings">
                                    <button type="submit"
                                            class="rounded-[10px] px-3 py-1 text-xs font-medium bg-red-100 text-red-600 hover:bg-red-200 transition-colors">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($listings)): ?>
                    <tr>
                        <td colspan="10" class="px-4 py-8 text-center text-[#8B8181] text-sm">No listings found.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ================================================================
             OFFERS TAB
        ================================================================ -->
        <?php elseif ($tab === 'offers'): ?>
        <div class="overflow-x-auto">
            <table class="w-full border-collapse bg-white rounded-[20px] border border-[#BFBABA] overflow-hidden">
                <thead class="bg-[#4368E5] text-white text-sm">
                    <tr>
                        <th class="px-4 py-3 text-left">ID</th>
                        <th class="px-4 py-3 text-left">Listing</th>
                        <th class="px-4 py-3 text-left">Buyer</th>
                        <th class="px-4 py-3 text-left">Seller</th>
                        <th class="px-4 py-3 text-left">Amount</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-left">Counter</th>
                        <th class="px-4 py-3 text-left">Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($offers as $o): ?>
                    <tr class="border-b border-[#BFBABA] hover:bg-[#F6F5F2]">
                        <td class="px-4 py-3 text-sm text-[#595959]"><?= $o['id'] ?></td>
                        <td class="px-4 py-3 text-sm font-medium text-gray-900 max-w-[180px] truncate">
                            <a href="listing.php?id=<?= $o['listing_id'] ?>" class="hover:text-[#4368E5] transition-colors">
                                <?= htmlspecialchars($o['listing_title']) ?>
                            </a>
                        </td>
                        <td class="px-4 py-3 text-sm text-[#595959]"><?= htmlspecialchars($o['buyer_name']) ?></td>
                        <td class="px-4 py-3 text-sm text-[#595959]"><?= htmlspecialchars($o['seller_name']) ?></td>
                        <td class="px-4 py-3 text-sm font-semibold text-gray-900">$<?= number_format($o['amount'], 2) ?></td>
                        <td class="px-4 py-3 text-sm">
                            <?php
                            $offerStatusClasses = [
                                'pending'   => 'bg-yellow-100 text-yellow-700',
                                'accepted'  => 'bg-green-100 text-green-700',
                                'rejected'  => 'bg-red-100 text-red-600',
                                'countered' => 'bg-blue-100 text-blue-700',
                            ];
                            $osc = $offerStatusClasses[$o['status']] ?? 'bg-gray-100 text-gray-500';
                            ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold <?= $osc ?>">
                                <?= ucfirst($o['status']) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-[#595959]">
                            <?= $o['counter_amount'] ? '$' . number_format($o['counter_amount'], 2) : '—' ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-[#595959]"><?= date('M j, Y', strtotime($o['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($offers)): ?>
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-[#8B8181] text-sm">No offers found.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </div>
</main>

<!-- FOOTER -->
<footer class="border-t-[3px] border-[#8B8181] bg-white mt-auto">
    <div class="max-w-6xl mx-auto px-6 py-10">
        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-8">
            <div class="max-w-xs">
                <div class="font-bold text-2xl text-[#4368E5] mb-2">CloseRate</div>
                <p class="text-sm text-[#8B8181] leading-relaxed">
                    The smarter way to buy and sell. Make offers, negotiate prices, and close deals with confidence.
                </p>
            </div>
            <div class="flex gap-12">
                <div>
                    <div class="text-sm font-semibold text-gray-900 mb-3">Marketplace</div>
                    <ul class="space-y-2 text-sm text-[#595959]">
                        <li><a href="listings.php" class="hover:text-[#4368E5] transition-colors">Browse Listings</a></li>
                        <li><a href="sell.php" class="hover:text-[#4368E5] transition-colors">Sell an Item</a></li>
                        <li><a href="dashboard.php" class="hover:text-[#4368E5] transition-colors">My Dashboard</a></li>
                    </ul>
                </div>
                <div>
                    <div class="text-sm font-semibold text-gray-900 mb-3">Account</div>
                    <ul class="space-y-2 text-sm text-[#595959]">
                        <li><a href="login.php" class="hover:text-[#4368E5] transition-colors">Sign In</a></li>
                        <li><a href="register.php" class="hover:text-[#4368E5] transition-colors">Sign Up</a></li>
                        <li><a href="logout.php" class="hover:text-[#4368E5] transition-colors">Log Out</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="mt-10 pt-6 border-t border-[#BFBABA] flex flex-col md:flex-row md:items-center md:justify-between gap-2">
            <p class="text-xs text-[#8B8181]">&copy; <?= date('Y') ?> CloseRate. All rights reserved.</p>
            <p class="text-xs text-[#8B8181]">Built for fast, fair negotiations.</p>
        </div>
    </div>
</footer>

</body>
</html>
