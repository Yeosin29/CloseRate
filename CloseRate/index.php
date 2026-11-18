<?php
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: " . ($_SESSION['role'] === 'admin' ? 'admin-dashboard.php' : 'home.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter both email and password.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];

            header("Location: " . ($user['role'] === 'admin' ? 'admin-dashboard.php' : 'home.php'));
            exit;
        } else {
            $error = 'Incorrect email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>CloseRate — Sign in</title>
	<script src="https://cdn.tailwindcss.com"></script>
	<style>
		body { font-family: system-ui; }
	</style>
</head>
<body>
	<div class="min-h-screen grid grid-cols-1 md:grid-cols-2">

		<div class="flex items-center justify-center px-6 py-10 md:px-16">
			<div class="w-full max-w-md">
				<h1 class="text-black text-4xl font-bold mb-10 text-center md:text-left">
					CloseRate
				</h1>

				<p class="text-black text-xl text-center mb-6">
					Please enter your details
				</p>

				<?php if (isset($_GET['registered'])): ?>
					<div class="text-center mb-5">
						<span class="text-green-600 text-base">Account created. Please log in.</span>
					</div>
				<?php endif; ?>

				<?php if ($error): ?>
					<div class="text-center mb-5">
						<span class="text-red-600 text-base"><?= htmlspecialchars($error) ?></span>
					</div>
				<?php endif; ?>

				<button type="button"
					class="w-full bg-white text-black text-lg py-4 mb-8 rounded-full border border-[#000000A1] hover:bg-gray-50"
					onclick="alert('Google login not connected yet')">
					Log in with Gmail
				</button>

				<div class="flex items-center gap-4 mb-8">
					<div class="flex-1 h-px bg-[#000000DE]"></div>
					<span class="text-black text-base">or</span>
					<div class="flex-1 h-px bg-[#000000DE]"></div>
				</div>

				<form method="POST" action="index.php" class="w-full">
					<div class="flex justify-between items-center w-full bg-white py-4 px-6 mb-5 rounded-full border border-[#000000A1]">
						<input type="email" name="email" placeholder="Email" required
							class="text-black text-lg w-full bg-transparent outline-none placeholder-black" />
						<span class="text-gray-400 text-lg ml-3">👤</span>
					</div>

					<div class="flex justify-between items-center w-full bg-white py-4 px-6 mb-5 rounded-full border border-[#000000A1]">
						<input type="password" name="password" placeholder="Password" required
							class="text-black text-lg w-full bg-transparent outline-none placeholder-black" />
						<span class="text-gray-400 text-lg ml-3">🔒</span>
					</div>

					<a href="forgot-password.php" class="block text-black text-base mb-6">
						Forgot Password?
					</a>

					<button type="submit"
						class="w-full bg-[#4368E5] text-white text-lg py-4 mb-6 rounded-full border border-[#000000A1] hover:bg-[#3657c7]">
						Log in
					</button>
				</form>

				<p class="text-black text-base text-center md:text-left">
					Don't have an account? <a href="register.php" class="font-semibold text-[#4368E5]">Sign up</a>
				</p>
			</div>
		</div>

		<div class="hidden md:flex relative bg-[#4368E5] flex-col items-center justify-center px-12 text-white text-center">
    <svg class="w-24 h-24 mb-8 opacity-90" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round">
        <path d="M3 17l6-6 4 4 8-8"/>
        <path d="M15 7h6v6"/>
    </svg>
    <h2 class="text-3xl font-bold mb-4">Welcome back</h2>
    <p class="text-white/80 text-lg max-w-xs">Track offers, negotiate fair deals, and close with confidence.</p>
    <div class="mt-10 flex gap-6 text-white/60 text-sm">
        <div class="flex flex-col items-center">
            <span class="text-white text-2xl font-bold">500+</span>
            <span>Listings</span>
        </div>
        <div class="w-px bg-white/30"></div>
        <div class="flex flex-col items-center">
            <span class="text-white text-2xl font-bold">92%</span>
            <span>Close Rate</span>
        </div>
        <div class="w-px bg-white/30"></div>
        <div class="flex flex-col items-center">
            <span class="text-white text-2xl font-bold">Fair</span>
            <span>Offers</span>
        </div>
    </div>
</div>

	</div>
</body>
</html>