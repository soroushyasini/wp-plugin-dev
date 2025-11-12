<div class="w-full mx-auto hamnaghsheh-dashboard flex items-center justify-center">
    <div class="max-w-2xl mt-20 bg-white rounded-2xl shadow p-6 text-center" style="width: 100%;max-width: 100%;">
        <h1 class="text-2xl font-bold text-[#09375B] mb-3">پروژه: <?php echo esc_html($project->name); ?></h1>
        <p class="text-gray-600 mb-5">شما از طریق لینک اشتراک وارد این صفحه شده‌اید.</p>

        <?php if ($is_guest): ?>

            <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 mb-6">
                <p class="text-gray-700 text-sm mb-4"><?php echo esc_html($project->description); ?></p>
                <p class="text-sm text-green-600">دسترسی مهمان فعال است ✅</p>
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
                                        <a href="<?php echo esc_url(home_url($f['file_path'])); ?>" download
                                            class="bg-[#FFCF00] hover:bg-[#e6bd00] text-[#09375B] px-3 py-1 rounded-lg text-xs font-semibold transition flex items-center justify-center">دانلود</a>

                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center py-6 text-gray-500">هیچ فایلی برای این پروژه ثبت نشده است.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php else: ?>

        <?php endif; ?>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="hamnaghsheh_assign_project">
            <input type="hidden" name="token" value="<?php echo esc_attr($share->token); ?>">
            <input type="hidden" name="project_id" value="<?php echo esc_attr($share->project_id); ?>">

            <?php if (is_user_logged_in()): ?>
                <button type="submit"
                    class="bg-[#09375B] text-white px-5 py-2 rounded-lg hover:bg-[#072c48] transition mt-5">
                    ورود به پروژه
                </button>
            <?php else: ?>
                <a href="<?php echo home_url('/login/?redirect_to=' . urlencode(site_url('/share/' . $share->token))); ?>"
                    class="inline-block bg-[#FFCF00] text-[#09375B] px-5 py-2 rounded-lg font-semibold hover:bg-yellow-400 transition mt-5">
                    ورود یا ثبت‌نام
                </a>
            <?php endif; ?>
        </form>
    </div>

</div>