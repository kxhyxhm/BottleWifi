<?php
session_start();

// Predefined admin credentials (in a real app, these would be stored securely in a database)
$admin_username = "admin";
$admin_password = "admin123"; // In production, use hashed passwords

// Handle login form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST["username"] ?? "";
    $password = $_POST["password"] ?? "";
    $remember = isset($_POST["remember"]);

    // Validate credentials
    if ($username === $admin_username && $password === $admin_password) {
        // Set session variables
        $_SESSION["logged_in"] = true;
        $_SESSION["username"] = $username;
        
        // Set remember-me cookie if requested
        if ($remember) {
            setcookie("remember_token", base64_encode($username), time() + (30 * 24 * 60 * 60), "/");
        }

        // Redirect to dashboard
        header("Location: dashboard.html");
        exit();
    } else {
        // Invalid credentials, redirect back with error
        header("Location: login.html?error=" . urlencode("Invalid username or password"));
        exit();
    }
}

// Check if user is already logged in
if (isset($_SESSION["logged_in"]) && $_SESSION["logged_in"] === true) {
    header("Location: dashboard.html");
    exit();
}

// Check remember-me cookie
if (isset($_COOKIE["remember_token"])) {
    $stored_username = base64_decode($_COOKIE["remember_token"]);
    if ($stored_username === $admin_username) {
        $_SESSION["logged_in"] = true;
        $_SESSION["username"] = $stored_username;
        header("Location: dashboard.html");
        exit();
    }
}
?>