<div class="flex items-center justify-center hamnaghsheh-dashboard">
  <div class="bg-white shadow-2xl rounded-2xl w-full max-w-md p-8">
    <h2 class="text-center text-2xl font-bold text-[#09375B] mb-6">ایجاد حساب جدید</h2>
    <?php if (isset($_GET['register']) && $_GET['register'] === 'exists'): ?>
      <div class="mb-5 bg-red-50 border border-red-200 text-red-700 text-xs rounded-lg p-3 text-center animate-fadeIn">
        نام کاربری یا ایمیل قبلا در سیستم ثبت شده است
      </div>
    <?php endif; ?>


    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="space-y-5">
      <input type="hidden" name="action" value="hamnaghsheh_register">

      <div>
        <label for="username" class="block mb-1 text-sm font-medium text-[#09375B]">نام کاربری</label>
        <input type="text" id="username" name="username" required
          class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-[#FFCF00] outline-none">
      </div>

      <div>
        <label for="email" class="block mb-1 text-sm font-medium text-[#09375B]">ایمیل</label>
        <input type="email" id="email" name="email" required
          class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-[#FFCF00] outline-none">
      </div>

      <div>
        <label for="password" class="block mb-1 text-sm font-medium text-[#09375B]">رمز عبور</label>
        <input type="password" id="password" name="password" required
          class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-[#FFCF00] outline-none">
      </div>

      <button type="submit"
        class="w-full bg-[#FFCF00] hover:bg-[#f4c700] text-[#09375B] font-semibold py-3 rounded-lg transition-all duration-200">
        ثبت نام
      </button>

      <p class="text-center text-sm text-gray-500">
        قبلاً حساب دارید؟
        <a href="<?php echo esc_url(home_url('/login/')); ?>" class="text-[#09375B] font-medium no-underline">
          وارد شوید
        </a>
      </p>
    </form>
  </div>
</div>