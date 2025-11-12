<?php
if (!defined('ABSPATH'))
    exit;

$current_user = wp_get_current_user();
$projects = Hamnaghsheh_Projects::get_user_projects($current_user->ID);

?>

<aside class="w-full lg:w-56 bg-[#09375B] rounded-2xl p-4 flex flex-col items-center text-center text-white shadow-lg">

    <!-- آواتار -->
    <div class="w-20 h-20 rounded-full overflow-hidden mb-3 border-4 border-[#FFCF00]">
        <img src="<?php echo esc_url(get_avatar_url($current_user->ID)); ?>" alt="Avatar"
            class="w-full h-full object-cover">
    </div>

    <!-- نام کاربر -->
    <?php
    $display_name = trim($current_user->user_firstname . ' ' . $current_user->user_lastname);
    ?>
    <div class="mb-4">
        <?php if (!empty($display_name)): ?>
            <div class="font-semibold text-base text-[#FFCF00]"><?php echo esc_html($display_name); ?></div>
            <div class="text-xs text-gray-300 mt-1"><?php echo esc_html($current_user->user_login); ?></div>
        <?php else: ?>
            <div class="font-semibold text-base text-[#FFCF00]"><?php echo esc_html($current_user->user_login); ?></div>
        <?php endif; ?>
    </div>

    <a href="<?php echo get_site_url() . '/dashboard'; ?>"
        class="mb-4 block text-white hover:text-[#FFCF00] text-sm truncate transition-colors outline-none">
        پروژه‌ها
    </a>
    <a href="<?php echo get_site_url() . '/my-account/profile'; ?>"
        class="mb-4 block text-white hover:text-[#FFCF00] text-sm truncate transition-colors outline-none">
        پروفایل
    </a>
    <a href="<?php echo get_site_url() . '/my-account/orders'; ?>"
        class="mb-4 block text-white hover:text-[#FFCF00] text-sm truncate transition-colors outline-none">
        خدمات خریداری شده
    </a>
    <!-- پروژه‌ها -->
    <div class="w-full text-right mb-10">
        <h3 class="text-sm font-semibold mb-2 text-[#FFCF00] border-b border-[#FFCF00]/40 pb-1">پروژه‌ها</h3>
        <?php if ($projects): ?>
            <ul class="space-y-1">
                <?php foreach ($projects as $p): ?>
                    <li>
                        <a href="<?php echo get_site_url() . '/show-project?id=' . esc_attr($p->id); ?>"
                            class="block text-white hover:text-[#FFCF00] text-xs truncate transition-colors outline-none">
                            • <?php echo esc_html($p->name) . ' - ' . esc_html($p->owner_name) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="text-xs text-gray-300">پروژه‌ای وجود ندارد</p>
        <?php endif; ?>
    </div>

    <?php if ($role): ?>

        <!-- اعضای پروژه -->
        <div class="w-full text-right mb-6">
            <h3 class="text-sm font-semibold mb-2 text-[#FFCF00] border-b border-[#FFCF00]/40 pb-1">اعضای پروژه</h3>
            <?php if ($members): ?>
                <ul class="space-y-1">
                    <?php foreach ($members as $member): ?>
                        <li class="text-xs text-gray-200 flex items-center justify-between">
                            <div>
                                <span class="text-[#FFCF00]">•</span>
                                <?php echo esc_html($member->user_name); ?>
                            </div>
                            <div>
                                <?php if ($can_manage): ?>
                                    <form action="<?= esc_url(admin_url('admin-post.php')) ?>" method="post">
                                        <input type="hidden" name="action" value="hamnaghsheh_unassigned">
                                        <input type="hidden" name="project_id" value="<?= esc_attr($project->id) ?>">
                                        <input type="hidden" name="user_id" value="<?= esc_attr($member->user_id) ?>">
                                        <button type="submit" class="text-[10px] text-slate-400" style="    background: transparent;
    color: white;
    font-size: 10px;
    padding: 0;">
                                            حذف
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="text-xs text-gray-300">عضوی ثبت نشده</p>
            <?php endif; ?>
        </div>

    <?php endif; ?>
    <!-- حجم مصرفی -->
    <div class="w-full mt-auto">
        <h3 class="text-xs font-semibold mb-2 text-[#FFCF00]">حجم مصرفی</h3>
        <div class="w-full bg-white/20 rounded-full h-2 mb-1">
            <div class="bg-[#FFCF00] h-2 rounded-full transition-all duration-300"
                style="width: <?php echo esc_attr($percent); ?>%;"></div>
        </div>
        <p class="text-[11px] mt-2 text-gray-200">
            <?php echo esc_html($used_human . ' از ' . $total_human); ?>
        </p>
    </div>

</aside>