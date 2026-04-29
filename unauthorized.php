<?php
require_once 'inc/permissions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
include 'inc/header.php';
include 'inc/sidebar.php';
?>
<main class="flex-1 flex items-center justify-center bg-slate-100 min-h-screen">
  <div class="text-center max-w-lg px-6 py-16">
    <div class="w-24 h-24 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-6">
      <i class="fas fa-shield-alt text-4xl text-red-500"></i>
    </div>
    <h1 class="text-3xl font-extrabold text-gray-800 mb-3">Access Restricted</h1>
    <p class="text-gray-500 text-base mb-2 leading-relaxed">
      You have <strong class="text-red-600">no authority</strong> to access this module.
    </p>
    <p class="text-gray-400 text-sm mb-8">
      Please contact your System Administrator to request access permissions.
    </p>
    <a href="index.php"
       class="inline-flex items-center gap-2 bg-blue-700 hover:bg-blue-800 text-white font-semibold px-6 py-3 rounded-xl shadow-lg transition">
      <i class="fas fa-home"></i> Return to Dashboard
    </a>
  </div>
</main>
<?php include 'inc/footer.php'; ?>
