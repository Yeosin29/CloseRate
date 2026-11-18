<?php
require_once 'config.php';

// Get filters from GET
$category  = isset($_GET['category']) ? trim($_GET['category']) : '';
$search    = isset($_GET['search'])   ? trim($_GET['search'])   : '';
$page      = isset($_GET['page'])     ? max(1, (int)$_GET['page']) : 1;
$perPage   = 12;
$offset    = ($page - 1) * $perPage;

$validCategories = ['electronics', 'furniture', 'kitchen', 'fashion', 'other'];

// Build WHERE clauses
$where    = ["listings.status = 'active'"];
$params   = [];

if ($category && in_array($category, $validCategories)) {
    $where[]          = "listings.category = :category";
    $params[':category'] = $category;
}

if ($search !== '') {
    $where[]         = "(listings.title LIKE :search OR listings.description LIKE :search2)";
    $params[':search']  = '%' . $search . '%';
    $params[':search2'] = '%' . $search . '%';
}

$whereSQL = 'WHERE ' . implode(' AND ', $where);

// Count total
$countSQL = "SELECT COUNT(*) FROM listings JOIN users ON listings.user_id = users.id $whereSQL";
$stmtCount = $pdo->prepare($countSQL);
$stmtCount->execute($params);
$total     = (int)$stmtCount->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

// Fetch listings
$sql = "SELECT listings.*, users.name AS seller_name
        FROM listings
        JOIN users ON listings.user_id = users.id
        $whereSQL
        ORDER BY listings.created_at DESC
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
$stmt->execute();
$listings = $stmt->fetchAll();

// Category display info
$categoryLabels = [
    ''            => 'All',
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
    ''            => '🏷️',
];
$conditionColors = [
    'new'      => 'bg-green-100 text-green-700',
    'like_new' => 'bg-blue-100 text-blue-700',
    'good'     => 'bg-gray-100 text-gray-600',
    'fair'     => 'bg-yellow-100 text-yellow-700',
    'poor'     => 'bg-red-100 text-red-600',
];
$conditionLabels = [
    'new'      => 'New',
    'like_new' => 'Like New',
    'good'     => 'Good',
    'fair'     => 'Fair',
    'poor'     => 'Poor',
];

// Build query string helper
function buildQuery(array $overrides = []): string {
    $base = [];
    if (!empty($_GET['search']))   $base['search']   = $_GET['search'];
    if (!empty($_GET['category'])) $base['category'] = $_GET['category'];
    if (!empty($_GET['page']))     $base['page']     = $_GET['page'];
    return http_build_query(array_merge($base, $overrides));
}

$loggedIn = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Listings – CloseRate</title>
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
		<a href="listings.php" class="text-[#4368E5] font-medium">Listings</a>
		<a href="dashboard.php">Dashboard</a>
	</div>
</header>

<!-- ===== MAIN ===== -->
<main class="flex-1">
  <section class="max-w-6xl mx-auto px-6 py-10">

    <!-- Page heading -->
    <h1 class="text-[28px] font-bold text-gray-900 mb-6">Browse Listings</h1>

    <!-- Search bar -->
    <form method="GET" action="listings.php" class="flex gap-3 max-w-md">
      <?php if ($category): ?>
        <input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>">
      <?php endif; ?>
      <input
        type="text"
        name="search"
        value="<?= htmlspecialchars($search) ?>"
        placeholder="Search listings…"
        class="flex-1 rounded-full border border-[#BFBABA] px-5 py-3 text-sm focus:outline-none focus:border-[#4368E5]"
      />
      <button type="submit" class="bg-[#4368E5] text-white px-5 py-2 rounded-full text-sm font-medium hover:opacity-90">
        Search
      </button>
    </form>

    <!-- Category tabs -->
    <div class="flex gap-3 flex-wrap mt-6">
      <?php foreach ($categoryLabels as $val => $label):
        $isActive = ($category === $val);
        $qs = $val === '' ? http_build_query(array_filter(['search' => $search, 'page' => null])) : http_build_query(array_filter(['category' => $val, 'search' => $search]));
        $activeClass   = 'bg-[#4368E5] text-white px-5 py-2 rounded-full text-sm font-medium';
        $inactiveClass = 'bg-white border border-[#BFBABA] px-5 py-2 rounded-full text-sm text-[#595959] hover:border-[#4368E5]';
      ?>
        <a href="listings.php?<?= $qs ?>" class="<?= $isActive ? $activeClass : $inactiveClass ?>">
          <?= htmlspecialchars($label) ?>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- Results info -->
    <p class="text-sm text-[#8B8181] mt-6">
      <?= $total ?> listing<?= $total !== 1 ? 's' : '' ?> found
      <?php if ($search): ?> for "<span class="text-[#595959]"><?= htmlspecialchars($search) ?></span>"<?php endif; ?>
      <?php if ($category): ?> in <span class="text-[#595959]"><?= htmlspecialchars($categoryLabels[$category] ?? $category) ?></span><?php endif; ?>
    </p>

    <?php if (empty($listings)): ?>
    <!-- Empty state -->
    <div class="mt-16 text-center">
      <div class="text-7xl mb-4">🔍</div>
      <h2 class="text-xl font-semibold text-gray-700 mb-2">No listings found</h2>
      <p class="text-[#8B8181] mb-6">Try adjusting your filters or search term.</p>
      <a href="listings.php" class="bg-[#4368E5] text-white px-6 py-2 rounded-full text-sm font-medium hover:opacity-90">
        Clear filters
      </a>
    </div>

    <?php else: ?>

    <!-- Listings grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5 mt-8">
      <?php foreach ($listings as $listing):
        $cat   = $listing['category'] ?? 'other';
        $emoji = $categoryEmojis[$cat]  ?? '📦';
        $cond  = $listing['condition_type'] ?? 'good';
        $condColor = $conditionColors[$cond] ?? 'bg-gray-100 text-gray-600';
        $condLabel = $conditionLabels[$cond] ?? ucfirst($cond);
      ?>
      <div class="bg-white rounded-[20px] border border-[#BFBABA] p-5 flex flex-col gap-3 hover:border-[#4368E5] transition">
        <!-- Image placeholder -->
        <?php if (!empty($listing['image_url'])): ?>
          <img src="<?= htmlspecialchars($listing['image_url']) ?>" alt="<?= htmlspecialchars($listing['title']) ?>" class="h-40 w-full object-cover rounded-[14px]" />
        <?php else: ?>
          <div class="h-40 bg-[#F6F5F2] rounded-[14px] flex items-center justify-center text-5xl">
            <?= $emoji ?>
          </div>
        <?php endif; ?>

        <!-- Title -->
        <h3 class="font-semibold text-lg truncate" title="<?= htmlspecialchars($listing['title']) ?>">
          <?= htmlspecialchars($listing['title']) ?>
        </h3>

        <!-- Badges row -->
        <div class="flex gap-2 flex-wrap items-center">
          <span class="bg-[#4368E5]/10 text-[#4368E5] text-xs px-2 py-1 rounded-full font-medium">
            <?= htmlspecialchars($categoryLabels[$cat] ?? ucfirst($cat)) ?>
          </span>
          <span class="<?= $condColor ?> text-xs px-2 py-1 rounded-full font-medium">
            <?= htmlspecialchars($condLabel) ?>
          </span>
        </div>

        <!-- Price -->
        <p class="text-[#4368E5] text-xl font-bold">
          $<?= number_format((float)$listing['price'], 2) ?>
        </p>

        <!-- Seller -->
        <p class="text-[#8B8181] text-sm">by <?= htmlspecialchars($listing['seller_name']) ?></p>

        <!-- View button -->
        <a
          href="listing.php?id=<?= (int)$listing['id'] ?>"
          class="bg-[#4368E5] text-white text-sm py-2 px-4 rounded-[14px] hover:opacity-90 w-full text-center mt-auto"
        >
          View listing
        </a>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="flex justify-center gap-2 mt-10 flex-wrap">
      <?php if ($page > 1): ?>
        <a href="listings.php?<?= buildQuery(['page' => $page - 1]) ?>"
           class="bg-white border border-[#BFBABA] px-4 py-2 rounded-full text-sm text-[#595959] hover:border-[#4368E5]">
          ← Prev
        </a>
      <?php endif; ?>

      <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
        <?php if ($p === $page): ?>
          <span class="bg-[#4368E5] text-white px-4 py-2 rounded-full text-sm font-medium">
            <?= $p ?>
          </span>
        <?php else: ?>
          <a href="listings.php?<?= buildQuery(['page' => $p]) ?>"
             class="bg-white border border-[#BFBABA] px-4 py-2 rounded-full text-sm text-[#595959] hover:border-[#4368E5]">
            <?= $p ?>
          </a>
        <?php endif; ?>
      <?php endfor; ?>

      <?php if ($page < $totalPages): ?>
        <a href="listings.php?<?= buildQuery(['page' => $page + 1]) ?>"
           class="bg-white border border-[#BFBABA] px-4 py-2 rounded-full text-sm text-[#595959] hover:border-[#4368E5]">
          Next →
        </a>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>
  </section>
</main>

<!-- ===== FOOTER ===== -->
<footer class="border-t-[3px] border-[#8B8181] bg-white mt-10">
  <div class="max-w-6xl mx-auto px-6 py-8 flex flex-col sm:flex-row items-center justify-between gap-4">
    <span class="font-bold text-2xl text-[#4368E5]">CloseRate</span>
    <p class="text-[#8B8181] text-sm">&copy; <?= date('Y') ?> CloseRate. All rights reserved.</p>
    <div class="flex gap-5 text-sm text-[#595959]">
      <a href="listings.php" class="hover:text-[#4368E5]">Listings</a>
      <a href="dashboard.php" class="hover:text-[#4368E5]">Dashboard</a>
    </div>
  </div>
</footer>

</body>
</html>
