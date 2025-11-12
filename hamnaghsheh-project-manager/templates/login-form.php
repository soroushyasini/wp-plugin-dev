<div class="flex items-center justify-center hamnaghsheh-dashboard">
    <div class="bg-white shadow-2xl rounded-2xl w-full max-w-md p-8">
        <h2 class="text-center text-2xl font-bold text-[#09375B] mb-6">ورود به حساب کاربری</h2>
        <?php if (isset($_GET['login']) && $_GET['login'] === 'failed'): ?>
            <div
                class="mb-5 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg p-3 text-center animate-fadeIn">
                نام کاربری یا رمز عبور اشتباه است.
            </div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="space-y-5">
            <input type="hidden" name="action" value="hamnaghsheh_login">
            <input type="hidden" name="redirect" value="<?= isset($_GET['redirect_to']) ?  $_GET['redirect_to'] :'' ?>">
            <div>
                <label for="log" class="block mb-1 text-sm font-medium text-[#09375B]">نام کاربری یا ایمیل</label>
                <input type="text" id="log" name="log" required
                    class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-[#FFCF00] outline-none">
            </div>

            <div>
                <label for="pwd" class="block mb-1 text-sm font-medium text-[#09375B]">رمز عبور</label>
                <input type="password" id="pwd" name="pwd" required
                    class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-[#FFCF00] outline-none">
            </div>

            <button type="submit"
                class="w-full bg-[#09375B] hover:bg-[#0b4c7a] text-white font-semibold py-3 rounded-lg transition-all duration-200">
                ورود
            </button>

            <p class="text-center text-sm text-gray-500">
                حساب کاربری ندارید؟
                <a href="<?php echo esc_url(home_url('/register/')); ?>"
                    class="text-yellow-500 font-medium no-underline">
                    ثبت نام کنید
                </a>
            </p>
        </form>
    </div>
</div>