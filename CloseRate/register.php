<?php
require_once 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($email === '' || $password === '' || $confirmPassword === '') {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);

        if ($check->fetch()) {
            $error = 'That email is already registered.';
        } else {
            $name = ucfirst(strstr($email, '@', true));

            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'user')");
            $stmt->execute([$name, $email, $hashed]);

            header("Location: index.php?registered=1");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>CloseRate — Sign up</title>
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

				<?php if ($error): ?>
					<div class="text-center mb-5">
						<span class="text-red-600 text-base"><?= htmlspecialchars($error) ?></span>
					</div>
				<?php endif; ?>

				<button type="button"
					class="w-full bg-white text-black text-lg py-4 mb-8 rounded-full border border-[#000000A1] hover:bg-gray-50"
					onclick="alert('Google sign-up not connected yet')">
					Sign in with Gmail
				</button>

				<div class="flex items-center gap-4 mb-8">
					<div class="flex-1 h-px bg-[#000000DE]"></div>
					<span class="text-black text-base">or</span>
					<div class="flex-1 h-px bg-[#000000DE]"></div>
				</div>

				<form method="POST" action="register.php" class="w-full">
					<div class="flex justify-between items-center w-full bg-white py-4 px-6 mb-5 rounded-full border border-[#000000A1]">
						<input type="email" name="email" placeholder="Email" required
							class="text-black text-lg w-full bg-transparent outline-none placeholder-black" />
						<span class="text-gray-400 text-lg ml-3">👤</span>
					</div>

					<div class="flex justify-between items-center w-full bg-white py-4 px-6 mb-5 rounded-full border border-[#000000A1]">
						<input type="password" name="password" placeholder="Password" required minlength="6"
							class="text-black text-lg w-full bg-transparent outline-none placeholder-black" />
						<span class="text-gray-400 text-lg ml-3">🔒</span>
					</div>

					<div class="flex justify-between items-center w-full bg-white py-4 px-6 mb-6 rounded-full border border-[#000000A1]">
						<input type="password" name="confirm_password" placeholder="Confirm Password" required minlength="6"
							class="text-black text-lg w-full bg-transparent outline-none placeholder-black" />
						<span class="text-gray-400 text-lg ml-3">🔒</span>
					</div>

					<button type="submit"
						class="w-full bg-[#4368E5] text-white text-lg py-4 mb-6 rounded-full border border-[#000000A1] hover:bg-[#3657c7]">
						Sign up
					</button>
				</form>

				<p class="text-black text-base text-center md:text-left">
					Already have an account? <a href="index.php" class="font-semibold text-[#4368E5]">Sign in</a>
				</p>
			</div>
		</div>

		<div class="hidden md:flex relative bg-[#4368E5] flex-col items-center justify-center px-12 text-white text-center">
    <svg class="w-24 h-24 mb-8 opacity-90" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 5v14M5 12h14"/>
    </svg>
    <h2 class="text-3xl font-bold mb-4">Start selling today</h2>
    <p class="text-white/80 text-lg max-w-xs">List your items, get real offers, and see what similar items actually sell for.</p>
    <div class="mt-10 flex gap-6 text-white/60 text-sm">
        <div class="flex flex-col items-center">
            <span class="text-white text-2xl font-bold">Free</span>
            <span>To list</span>
        </div>
        <div class="w-px bg-white/30"></div>
        <div class="flex flex-col items-center">
            <span class="text-white text-2xl font-bold">Fast</span>
            <span>Setup</span>
        </div>
        <div class="w-px bg-white/30"></div>
        <div class="flex flex-col items-center">
            <span class="text-white text-2xl font-bold">Safe</span>
            <span>Deals</span>
        </div>
    </div>
</div>

	</div>
</body>
</html>