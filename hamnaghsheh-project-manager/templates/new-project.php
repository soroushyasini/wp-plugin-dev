<?php
if (!defined('ABSPATH'))
  exit;

$role = false;
?>

<div class="wrap hamnaghsheh-dashboard rounded-2xl p-5 lg:p-10">
  <div class="flex flex-col lg:flex-row gap-6">

    <?php include plugin_dir_path(__FILE__) . 'sidebar-dashboard.php'; ?>

    <main class="flex-1">
      <div class="mb-5 xl:mb-8 flex items-center justify-between">
        <div class="flex-1">
          <h1 class="font-black text-lg xl:text-2xl mb-3 text-[#09375B]">ایجاد پروژه جدید</h1>
          <p class="text-xs xl:text-sm text-gray-700">سامانه مدیریت پروژه‌های نقشه‌برداری هم‌نقشه</p>
        </div>
        <div>
          <a href="<?php echo get_site_url() . '/dashboard'; ?>"
            class="bg-[#FFCF00] hover:bg-[#e6bd00] text-[#09375B] font-bold py-2 px-4 text-sm rounded transition-all">
            بازگشت به پروژه‌ها
          </a>
        </div>
      </div>
      <hr class="border-gray-300 mb-5">

      <div class="rounded border border-slate-200">
        <div class="flex items-center justify-between rounded-t bg-[#09375B]/10 p-2">
          <h2 class="text-md xl:text-xl font-bold text-[#09375B]">فرم ایجاد پروژه جدید</h2>
        </div>

        <div class="min-h-80 p-2 xl:p-10 text-sm">
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="space-y-5">
            <input type="hidden" name="action" value="hamnaghsheh_create_project">
            <?php wp_nonce_field('hamnaghsheh_create_project', 'hamnaghsheh_nonce'); ?>

            <div>
              <label class="block text-gray-700 font-medium mb-1" for="project_name">نام پروژه</label>
              <input id="project_name" name="project_name" type="text" required placeholder="نام پروژه"
                class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-[#09375B] focus:border-[#09375B] transition-all" />
            </div>

            <div>
              <label class="block text-gray-700 font-medium mb-1" for="project_desc">توضیحات</label>
              <textarea id="project_desc" name="project_desc" required rows="4"
                placeholder="توضیح کوتاهی درباره پروژه بنویسید..."
                class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-[#09375B] focus:border-[#09375B] transition-all resize-none"></textarea>
            </div>

            <div>
              <label class="block text-gray-700 font-medium mb-1" for="type">نوع پروژه</label>
              <select name="project_type" id="type"
                class="w-full px-4 py-2 rounded-xl border border-gray-300 bg-white focus:ring-2 focus:ring-[#09375B] focus:border-[#09375B] transition-all">
                <option value="">انتخاب نوع</option>
                <option value="residential">ساخت‌وساز مسکونی</option>
                <option value="commercial">ساخت‌وساز تجاری</option>
                <option value="renovation">بازسازی</option>
                <option value="infrastructure">زیرساخت</option>
                <option value="other">سایر</option>
              </select>
            </div>

            <button type="submit"
              class="w-full bg-[#09375B] text-white py-3 rounded-xl font-semibold text-lg shadow-md hover:bg-[#062845] hover:shadow-lg transition-all">
              ایجاد پروژه
            </button>
          </form>
        </div>
      </div>
    </main>
  </div>
</div>
