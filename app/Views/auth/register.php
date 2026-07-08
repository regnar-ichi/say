<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register</title>
</head>
<body>

<?php if (!empty($error)): ?>
    <p><?php echo htmlspecialchars($error); ?></p>
<?php endif; ?>

<form method="post" action="/register">
    <input type="text" name="login" placeholder="Login" value="<?php echo htmlspecialchars($login ?? ''); ?>" required>
    <input type="password" name="password" placeholder="Password" required>
    <input type="password" name="password_confirm" placeholder="Confirm password" required>

    <button type="submit">Register</button>
</form>

<p>
    <a href="/login">Already have an account? Login</a>
</p>

</body>
</html>
