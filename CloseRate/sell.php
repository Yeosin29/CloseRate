<?php
require_once 'auth-check.php';
require_once 'config.php';

$userId = $_SESSION['user_id'];
$userName = $_SESSION['name'] ?? '';
$editing = false;
$listing = null;
$errors = [];

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $listingId = (int)($_POST['listing_id'] ?? 0);
        if ($listingId > 0) {
            $stmt = $pdo->prepare("DELETE FROM listings WHERE id = ? AND user_id = ?");
            $stmt->execute([$listingId, $userId]);
        }
        header("Location: dashboard.php");
        exit;
    }

    if ($action === 'save') {
        $listingId  = (int)($_POST['listing_id'] ?? 0);
        $title      = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category   = $_POST['category'] ?? 'other';
        $price      = $_POST['price'] ?? '';
        $condition  = $_POST['condition_type'] ?? 'good';

        $validCategories  = ['electronics','furniture','kitchen','fashion','other'];
        $validConditions  = ['new','like_new','good','fair','poor'];

        if ($title === '') $errors[] = 'Title is required.';
        if ($price === '' || !is_numeric($price) || (float)$price < 0) $errors[] = 'A valid price is required.';
        if (!in_array($category, $validCategories)) $category = 'other';
        if (!in_array($condition, $validConditions)) $condition = 'good';

        if (empty($errors)) {
            if ($listingId > 0) {
                // Verify ownership
                $check = $pdo->prepare("SELECT id FROM listings WHERE id = ? AND user_id = ?");
                $check->execute([$listingId, $userId]);
                if ($check->fetch()) {
                    $stmt = $pdo->prepare("UPDATE listings SET title=?, description=?, category=?, price=?, condition_type=?, updated_at=NOW() WHERE id=? AND user_id=?");
                    $stmt->execute([$title, $description, $category, (float)$price, $condition, $listingId, $userId]);
                    header("Location: listing.php?id=" . $listingId);
                    exit;
                } else {
                    $errors[] = 'Listing not found or access denied.';
                }
            } else {
                $stmt = $pdo->prepare("INSERT INTO listings (user_id, title, description, category, price, condition_type, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
                $stmt->execute([$userId, $title, $description, $category, (float)$price, $condition]);
                $newId = $pdo->lastInsertId();
                header("Location: listing.php?id=" . $newId);
                exit;
            }
        }

        // Re-populate form on error
        $listing = [
            'id'             => $listingId,
            'title'          => $title,
            'description'    => $description,
            'category'       => $category,
            'price'          => $price,
            'condition_type' => $condition,
        ];
        $editing = $listingId > 0;
    }
} elseif (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM listings WHERE id = ? AND user_id = ?");
    $stmt->execute([$editId, $userId]);
    $listing = $stmt->fetch();
    if ($listing) {
        $editing = true;
    } else {
        header("Location: dashboard.php");
        exit;
    }
}

$pageTitle = $editing ? 'Edit Listing' : 'List an Item';

$categories = [
    'electronics' => 'Electronics',
    'furniture'   => 'Furniture',
    'kitchen'     => 'Kitchen',
    'fashion'     => 'Fashion',
    'other'       => 'Other',
];

$conditions = [
    'new'      => 'New',
    'like_new' => 'Like New',
    'good'     => 'Good',
    'fair'     => 'Fair',
    'poor'     => 'Poor',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — CloseRate</title>
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
		<a href="listings.php" class="text-[#595959]">Listings</a>
		<a href="dashboard.php">Dashboard</a>
	</div>
</header>

<!-- Main -->
<main class="flex-1 bg-[#F6F5F2]">
    <section class="max-w-2xl mx-auto px-6 py-12">
        <h1 class="text-[28px] font-bold text-black mb-8"><?= htmlspecialchars($pageTitle) ?></h1>

        <?php if (!empty($errors)): ?>
            <div class="mb-6 bg-red-50 border border-red-200 rounded-[14px] px-5 py-4 text-red-600 text-sm">
                <ul class="list-disc list-inside space-y-1">
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-[30px] border border-[#BFBABA] p-8">
            <form method="POST" action="sell.php">
                <input type="hidden" name="action" value="save">
                <?php if ($editing && $listing): ?>
                    <input type="hidden" name="listing_id" value="<?= (int)$listing['id'] ?>">
                <?php endif; ?>

                <!-- Title -->
                <div class="mb-6">
                    <label class="block text-[#595959] text-sm mb-2 font-medium" for="title">Title</label>
                    <input
                        type="text"
                        id="title"
                        name="title"
                        required
                        value="<?= htmlspecialchars($listing['title'] ?? '') ?>"
                        placeholder="What are you selling?"
                        class="w-full border border-[#BFBABA] rounded-[14px] px-5 py-3 text-black outline-none focus:border-[#4368E5] bg-white transition-colors"
                    >
                </div>

                <!-- Description -->
                <div class="mb-6">
                    <label class="block text-[#595959] text-sm mb-2 font-medium" for="description">Description</label>
                    <textarea
                        id="description"
                        name="description"
                        rows="4"
                        placeholder="Describe your item — condition, features, any flaws..."
                        class="w-full border border-[#BFBABA] rounded-[14px] px-5 py-3 text-black outline-none focus:border-[#4368E5] bg-white transition-colors resize-none"
                    ><?= htmlspecialchars($listing['description'] ?? '') ?></textarea>
                </div>

                <!-- Category -->
                <div class="mb-6">
                    <label class="block text-[#595959] text-sm mb-2 font-medium" for="category">Category</label>
                    <select
                        id="category"
                        name="category"
                        class="w-full border border-[#BFBABA] rounded-[14px] px-5 py-3 text-black outline-none focus:border-[#4368E5] bg-white transition-colors appearance-none"
                    >
                        <option value="other" <?= (($listing['category'] ?? 'other') === 'other') ? 'selected' : '' ?>>All</option>
                        <option value="electronics" <?= (($listing['category'] ?? '') === 'electronics') ? 'selected' : '' ?>>Electronics</option>
                        <option value="furniture" <?= (($listing['category'] ?? '') === 'furniture') ? 'selected' : '' ?>>Furniture</option>
                        <option value="kitchen" <?= (($listing['category'] ?? '') === 'kitchen') ? 'selected' : '' ?>>Kitchen</option>
                        <option value="fashion" <?= (($listing['category'] ?? '') === 'fashion') ? 'selected' : '' ?>>Fashion</option>
                        <option value="other" <?= (($listing['category'] ?? '') === 'other') ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>

                <!-- Price -->
                <div class="mb-6">
                    <label class="block text-[#595959] text-sm mb-2 font-medium" for="price">Price ($)</label>
                    <input
                        type="number"
                        id="price"
                        name="price"
                        step="0.01"
                        min="0"
                        required
                        value="<?= htmlspecialchars($listing['price'] ?? '') ?>"
                        placeholder="0.00"
                        class="w-full border border-[#BFBABA] rounded-[14px] px-5 py-3 text-black outline-none focus:border-[#4368E5] bg-white transition-colors"
                    >
                </div>

                <!-- Condition -->
                <div class="mb-8">
                    <label class="block text-[#595959] text-sm mb-2 font-medium" for="condition_type">Condition</label>
                    <select
                        id="condition_type"
                        name="condition_type"
                        class="w-full border border-[#BFBABA] rounded-[14px] px-5 py-3 text-black outline-none focus:border-[#4368E5] bg-white transition-colors appearance-none"
                    >
                        <option value="new"      <?= (($listing['condition_type'] ?? '') === 'new')      ? 'selected' : '' ?>>New</option>
                        <option value="like_new" <?= (($listing['condition_type'] ?? '') === 'like_new') ? 'selected' : '' ?>>Like New</option>
                        <option value="good"     <?= (($listing['condition_type'] ?? 'good') === 'good') ? 'selected' : '' ?>>Good</option>
                        <option value="fair"     <?= (($listing['condition_type'] ?? '') === 'fair')     ? 'selected' : '' ?>>Fair</option>
                        <option value="poor"     <?= (($listing['condition_type'] ?? '') === 'poor')     ? 'selected' : '' ?>>Poor</option>
                    </select>
                </div>

                <!-- Submit -->
                <div class="flex items-center gap-4 flex-wrap">
                    <button
                        type="submit"
                        class="bg-[#4368E5] text-white py-3 px-8 rounded-full hover:opacity-90 text-lg font-medium transition-opacity"
                    >
                        <?= $editing ? 'Save Changes' : 'List Item' ?>
                    </button>
                    <a href="<?= $editing ? 'listing.php?id=' . (int)$listing['id'] : 'dashboard.php' ?>"
                       class="text-[#595959] hover:text-black text-sm underline">
                        Cancel
                    </a>
                </div>
            </form>
        </div>

        <?php if ($editing && $listing): ?>
        <!-- Delete form -->
        <div class="mt-6">
            <form method="POST" action="sell.php" onsubmit="return confirm('Are you sure you want to delete this listing? This cannot be undone.');">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="listing_id" value="<?= (int)$listing['id'] ?>">
                <button
                    type="submit"
                    class="bg-white border border-red-400 text-red-500 py-3 px-8 rounded-full hover:bg-red-50 transition-colors text-sm font-medium"
                >
                    Delete Listing
                </button>
            </form>
        </div>
        <?php endif; ?>
    </section>
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
