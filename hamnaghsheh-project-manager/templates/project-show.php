<?php
if (!defined('ABSPATH'))
  exit;
$role = true;

$permission = $project->user_permission;

$can_upload = in_array($permission, ['owner', 'upload']);

$can_manage = ($permission === 'owner');

?>

<div class="wrap hamnaghsheh-dashboard rounded-2xl p-5 lg:p-10">
  <div class="flex flex-col lg:flex-row gap-6">

    <?php include plugin_dir_path(__FILE__) . 'sidebar-dashboard.php'; ?>

    <main class="flex-1">

      <div class="mb-6">
        <h1 class="font-black text-xl xl:text-2xl mb-2 text-[#09375B]">
          <?php echo esc_html($project->name); ?>
        </h1>
        <p class="text-sm text-gray-600">مالک پروژه:
          <span class="font-semibold text-[#09375B]">
            <?php echo esc_html($project->display_name); ?>
          </span>
        </p>
      </div>
      <hr class="border-gray-300 mb-8">
      <?php if (!empty($_SESSION['alert'])): ?>
        <?php
        $alert = $_SESSION['alert'];
        $type = $alert['type'] === 'success' ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700';
        ?>
        <div class="border-l-4 p-4 rounded mb-6 text-sm <?php echo $type; ?>" role="alert">
          <p><?php echo esc_html($alert['message']); ?></p>
        </div>
        <?php unset($_SESSION['alert']); ?>
      <?php endif; ?>

      <?php if ($can_upload): ?>

        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" enctype="multipart/form-data"
          class="relative">
          <input type="hidden" name="action" value="hamnaghsheh_upload_file">
          <input type="hidden" name="project_id" value="<?php echo esc_attr($project->id); ?>">

          <label
            class="border-2 border-dashed border-[#09375B] rounded-2xl bg-[#F8FAFC] p-10 text-center mb-6 hover:bg-[#f2f6fb] transition block cursor-pointer relative overflow-hidden">
            <p class="text-[#09375B] font-semibold mb-2">فایل‌های خود را بکشید و در اینجا رها کنید</p>
            <p class="text-sm text-gray-500">یا برای انتخاب فایل‌ها کلیک کنید</p>

            <!-- ورودی فایل با شفافیت کامل -->
            <input type="file" name="file" required class="absolute inset-0 opacity-0 cursor-pointer"
              onchange="this.form.submit()">
          </label>
        </form>
      <?php endif; ?>

      <div class="flex flex-col lg:flex-row justify-between mb-8 space-y-1 lg:space-y-0">
        <?php
        if ($can_manage) {
          echo '<form action="' . esc_url(admin_url('admin-post.php')) . '" method="post" onsubmit="return confirmArchive();">
                  <input type="hidden" name="action" value="hamnaghsheh_archive_project">
                  <input type="hidden" name="project_id" value="'.esc_attr($project->id).'">

                  <button type="submit" class="bg-[#09375B] w-100 lg:w-100 text-sm outline-none hover:bg-[#072c48] text-white p-2 rounded transition">
                    📦 آرشیو پروژه
                  </button>
                </form>';
        }
        ?>

        <div class="flex-col flex lg:flex-row space-y-1 gap-0 lg:gap-3 lg:space-y-0">
          <?php if ($can_upload): ?>
            <button class="bg-[#FFCF00] text-sm outline-none hover:bg-[#e6bd00] text-[#09375B] p-2 rounded transition"
              onclick="downloadProjectFiles(<?= $project->id ?>)">⬇️ دانلود همه فایل‌ها</button>
          <?php endif; ?>
          <?php
          if ($can_manage) {
            echo "
              <button id='open-share-popup'
                class='bg-blue-600 hover:bg-blue-700 text-white text-sm  px-4 py-2 rounded transition-all duration-200'>
                🔗 ایجاد لینک اشتراک
              </button>
            ";
          }
          ?>
        </div>

        <div id="share-popup" style="z-index:1000;" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center">
          <div class="bg-white rounded-xl text-sm p-6 w-[600px]">
            <button type="button" id="close-share-popup" class="text-gray-600 text-2xl w-10 h-10"
              style="float: left;">×</button>
            <h2 class="text-lg font-bold mb-4">ساخت لینک اشتراک‌ گذاری</h2>

            <form id="share-form">
              <input type="hidden" name="project_id" value="<?php echo $project->id; ?>">
              <label>نوع اشتراک</label>
              <select name="permission" class="w-full border p-2 rounded mb-3">
                <option value="upload">دسترسی کامل(دانلود، مشاهده، جایگزینی)</option>
                <option value="view">دسترسی فقط مشاهده</option>
              </select>
              <button type="submit" class="block w-full mt-2 bg-green-600 text-white px-4 py-2 rounded">ساخت
                لینک</button>
            </form>

            <div id="share-links-list" class="mt-5">
              <h3 class="font-bold mb-2">لینک‌های ساخته شده:</h3>
              <?php
              $links = Hamnaghsheh_Share::get_share_links($project->id);
              if ($links) {
                echo "<div class='grid grid-cols-1 gap-4' style='max-height: 200px;overflow-y: scroll;'>";
                foreach ($links as $link) {
                  $url = site_url("/share/$link->token");
                  $permision = $link->permission == 'upload' ? 'دسترسی کامل' : 'فقط مشاهده';
                  echo "<div class='bg-white border border-gray-200 rounded-xl p-4 shadow-sm hover:shadow-md transition-all duration-200'>
                          <div class='flex items-center justify-between mb-2'>
                            <span class='text-sm font-medium text-gray-700'>لینک اشتراک ($permision)</span>
                            <button 
                              onclick=\"copyToClipboard('$url', this)\" 
                              class='text-xs bg-blue-100 text-blue-700 ouline-none px-3 py-1 rounded-lg hover:bg-blue-200 transition-all duration-150'>
                              کپی
                            </button>
                          </div>
                          <a href='$url' target='_blank' class='block text-blue-600 text-sm font-semibold truncate hover:underline mb-3'>$url</a>
                        </div>
                  ";
                }
                echo "</div>";
              } else {
                echo "<p class='text-gray-500 text-sm text-center bg-gray-50 border border-dashed border-gray-300 rounded-xl p-6'>هیچ لینکی ساخته نشده.</p>";
              }
              ?>

            </div>
          </div>
        </div>




      </div>

      <div class="overflow-x-auto rounded-2xl border border-gray-200 shadow-sm bg-white">
        <table class="min-w-full text-sm text-gray-700">
          <thead class="bg-[#09375B] text-white text-right">
            <tr>
              <th class="py-3 px-4 rounded-tr-2xl text-white">ردیف</th>
              <th class="py-3 px-4 text-white">نام فایل</th>
              <th class="py-3 px-4 rounded-tl-2xl text-center text-white">عملیات</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($files)): ?>
              <?php foreach ($files as $i => $f): ?>
                <tr class="border-b hover:bg-[#f9fafb] transition">
                  <td class="py-3 px-4"><?php echo $i + 1; ?></td>
                  <td class="py-3 px-4 font-medium text-[#09375B]">
                    <?php echo esc_html($f['file_name']); ?>
                    <span class="text-gray-500 text-xs">(<?php echo size_format($f['file_size']); ?>)</span>
                  </td>
                  <td class="py-3 px-4 flex flex-wrap gap-2 justify-center">
                    <?php if ($can_upload): ?>
                      <a href="<?php echo esc_url(home_url($f['file_path'])); ?>" download onclick="logDownload(<?php echo intval($f['id']); ?>, <?php echo intval($project->id); ?>)"
                        class="bg-[#FFCF00] hover:bg-[#e6bd00] text-[#09375B] px-3 py-1 rounded-lg text-xs font-semibold transition flex items-center justify-center">دانلود</a>
                    <?php endif; ?>
                    <a href="#" class="bg-slate-800 hover:bg-slate-900 text-white px-3 py-1 rounded-lg text-xs font-semibold transition flex items-center justify-center"  onclick="logSee(<?php echo intval($f['id']); ?>, <?php echo intval($project->id); ?>)">مشاهده</a>
                    <?php if ($can_manage): ?>
                      <button onclick="openFileLogsModal(<?php echo $f['id']; ?>)"
                        class="bg-[#09375B] hover:bg-[#072c48] text-white px-3 py-1 rounded-lg text-xs font-semibold transition flex items-center justify-center">سوابق</button>
                    <?php endif; ?>

                    <?php if ($can_upload): ?>
                      <button data-file-id="<?php echo esc_attr($f['id']); ?>"
                        class="replace-btn bg-[#0d4e80] hover:bg-[#09375B] text-white px-3 py-1 rounded-lg text-xs font-semibold transition flex items-center justify-center">
                        جایگزینی
                      </button>
                    <?php endif; ?>
                    <?php if ($can_manage): ?>
                      <a href="<?php echo esc_url(admin_url('admin-post.php?action=hamnaghsheh_delete_file&file_id=' . $f['id'] . '&project_id=' . $project->id)); ?>"
                        class=" flex items-center justify-center bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded-lg text-xs font-semibold transition"
                        onclick="return confirm('آیا از حذف این فایل مطمئن هستید؟');">
                        حذف
                      </a>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="3" class="text-center py-6 text-gray-500">هیچ فایلی برای این پروژه ثبت نشده است.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>




        <div id="fileLogsModal"
          class="fixed inset-0 hidden items-center justify-center z-50 inset-0 bg-black bg-opacity-50">
          <div class="bg-white rounded-2xl shadow-xl w-11/12 max-w-lg p-6 relative">
            <button onclick="closeFileLogsModal()"
              class="absolute top-2 left-3 text-gray-400 hover:text-gray-600 text-xl">×</button>
            <h2 class="text-lg font-bold mb-4 text-[#09375B]">سوابق فایل</h2>
            <div id="fileLogsContent" class="space-y-5 text-sm text-gray-700" style="
    max-height: 300px;
    overflow-y: scroll;
">
              <p class="text-center text-gray-400">در حال بارگذاری...</p>
            </div>
          </div>
        </div>

        <!-- Modal Background -->
        <div id="replaceModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
          <div class="bg-white rounded-2xl p-6 w-full max-w-md relative shadow-xl">
            <h2 class="text-lg font-bold mb-4 text-gray-800">جایگزینی فایل</h2>

            <form method="POST" action="<?php echo admin_url('admin-post.php'); ?>" enctype="multipart/form-data">
              <input type="hidden" name="action" value="hamnaghsheh_replace_file">
              <input type="hidden" name="file_id" id="replace_file_id">
              <input type="hidden" name="project_id" value="<?php echo esc_attr($project_id); ?>">

              <label class="block mb-2 text-sm text-gray-700 font-medium">فایل جدید را انتخاب کنید:</label>
              <input type="file" name="file" class="w-full border border-gray-300 rounded-lg p-2 text-sm mb-4" required>

              <div class="flex justify-end gap-2">
                <button type="button" id="closeModalBtn"
                  class="px-3 py-1 text-sm text-gray-600 bg-gray-200 rounded-lg hover:bg-gray-300">
                  لغو
                </button>
                <button type="submit" class="px-3 py-1 text-sm text-white bg-[#0d4e80] rounded-lg hover:bg-[#09375B]">
                  ثبت جایگزینی
                </button>
              </div>
            </form>
          </div>
        </div>




      </div>

    </main>

  </div>
</div>