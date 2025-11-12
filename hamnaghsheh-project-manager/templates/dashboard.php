<?php
if (!defined('ABSPATH'))
  exit;

$current_user = wp_get_current_user();
$projects = Hamnaghsheh_Projects::get_user_projects($current_user->ID);
$role = false;

?>

<div class="wrap hamnaghsheh-dashboard rounded-2xl p-5 lg:p-10">
  <div class="flex flex-col lg:flex-row gap-6">

    <?php include plugin_dir_path(__FILE__) . 'sidebar-dashboard.php'; ?>

    <main class="flex-1">
      <div class="mb-5 xl:mb-8 flex items-center justify-between">
        <div class="flex-1">
          <h1 class="font-black text-lg xl:text-2xl mb-3 text-[#09375B]">کارتابل من</h1>
        </div>
        <div class="flex items-center justify-center gap-2">
          <a href="<?php echo esc_url(site_url('/hamnaghsheh-purchase')); ?>"
            class="border bg-transparent text-slate-900 px-4 py-2 rounded text-sm">
            خرید اشتراک
          </a>
          <a class="bg-[#FFCF00] hover:bg-[#e6bd00] text-[#09375B] font-bold py-2 px-4 text-sm rounded transition-all"
            href="<?php echo get_site_url() . '/new-project'; ?>">ایجاد پروژه جدید</a>
        </div>

      </div>
      <hr class="border-gray-300 mb-5">

      <div class="rounded border border-slate-200">
        <div class="flex items-center justify-between rounded-t bg-[#09375B]/10 p-2" role="alert">
          <h2 class="text-md xl:text-xl font-bold text-[#09375B]">پروژه‌ها</h2>
        </div>

        <div class="min-h-80 p-2 xl:p-10">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <?php if ($projects): ?>
              <?php foreach ($projects as $p): ?>
                <div
                  class="rounded grid grid-cols-1 lg:grid-cols-8 p-3 border border-slate-300 lg:min-h-24 bg-white hover:shadow-lg transition-all relative ">


                  <div
                    class="col-span-1 h-16 lg:h-auto border border-slate-200 flex items-center justify-center bg-slate-100 rounded">
                    <img class="w-12 h-12" src="<?= HAMNAGHSHEH_URL . 'assets/img/' . $p->type . '.png' ?>" />
                  </div>

                  <div class="col-span-7 px-3 py-4 lg:py-2">
                    <div class="font-black text-center lg:text-right text-xl mb-2 text-[#09375B]">
                      <a class="outline-none break-words whitespace-normal"
                        href="<?php echo get_site_url() . '/show-project?id=' . esc_attr($p->id); ?>">
                        <?php echo esc_html($p->name); ?> -
                        <?php echo esc_html($p->owner_name); ?>
                      </a>
                    </div>
                    <p class="text-xs xl:text-sm text-gray-700 text-center lg:text-right ">
                      <?php echo esc_html($p->description); ?>
                    </p>
                  </div>

                  <div class="relative lg:absolute lg:left-5 lg:top-5 p-2 lg:p-0">
                    <div class="flex gap-2 flex-row-reverse items-center justify-start">
                      <?php
                      if ($p->is_owner) {
                        echo '<button 
                        onclick="openEditModal('
                          . '\'' . esc_js($p->id) . '\', '
                          . '\'' . esc_js($p->name) . '\', '
                          . '\'' . esc_js($p->description) . '\', '
                          . '\'' . esc_js($p->type) . '\''
                          . ')" 
                          class="text-sm border border-blue-600 text-blue-700 rounded px-3 outline-none hover:bg-blue-600 hover:text-white transition-all">
                          ویرایش
                        </button>';
                      }
                      ?>

                      <div>
                        <div class="rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700">
                          فعال
                        </div>
                      </div>
                    </div>
                  </div>

                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="col-span-2 rounded p-3 border border-slate-200 text-center min-h-40">
                <p>هنوز پروژه‌ای ایجاد نکرده‌اید</p>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($archived_project): ?>
          <div class="mt-6">
            <div class="flex items-center justify-between bg-[#09375B]/10 p-2" role="alert">
              <h2 class="text-md xl:text-xl font-bold text-[#09375B]">پروژه‌های آرشیو شده</h2>
            </div>
            <div class="space-y-1">
              <?php foreach ($archived_project as $p): ?>
                <div class="p-3 opacity-60 cursor-not-allowed flex items-center justify-between border">
                  <div>
                    <span class="text-[#09375B]"><?php echo esc_html($p->name); ?></span>
                  </div>
                  <div class="flex items-center justify-between gap-2">
                      <div class="text-xs text-gray-500 italic">آرشیو شده</div>
                      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="hamnaghsheh_unarchive_project">
                        <input type="hidden" name="project_id" value="<?php echo esc_html($p->id); ?>">
                        <button type="submit" class="text-xs rounded" style="display:none;">خارج کردن</button>
                      </form>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>


        <div>
        </div>
      </div>
    </main>
  </div>
  <!-- Modal -->
  <div id="editModal" class="fixed inset-0 hidden bg-black/50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl w-96 p-6 relative">
      <button onclick="closeEditModal()" class="absolute top-5 left-5 text-gray-500 hover:text-gray-700">✕</button>

      <h2 class="text-xl font-bold text-[#09375B] mb-4">ویرایش پروژه</h2>

      <form id="editProjectForm" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
        class="flex flex-col gap-3">
        <input type="hidden" name="project_id" id="edit_project_id">
        <input type="hidden" name="action" value="hamnaghsheh_update_project">
        <?php wp_nonce_field('hamnaghsheh_update_project', 'hamnaghsheh_nonce'); ?>

        <label class="text-sm text-gray-700">نام پروژه</label>
        <input type="text" name="project_name" id="edit_name"
          class="border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring focus:ring-[#FFCF00]">

        <label class="text-sm text-gray-700">توضیحات</label>
        <textarea name="project_desc" id="edit_description"
          class="border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring focus:ring-[#FFCF00]"></textarea>

        <label class="text-sm text-gray-700">نوع پروژه</label>
        <select name="project_type" id="edit_type"
          class="border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring focus:ring-[#FFCF00]">
          <option value="residential">ساخت‌وساز مسکونی</option>
          <option value="commercial">ساخت‌وساز تجاری</option>
          <option value="renovation">بازسازی</option>
          <option value="infrastructure">زیرساخت</option>
          <option value="other">سایر</option>
        </select>

        <button type="submit"
          class="bg-[#09375B] text-white text-sm rounded py-2 mt-3 hover:bg-[#062a45] transition-all">
          ذخیره تغییرات
        </button>
      </form>
    </div>
  </div>

</div>