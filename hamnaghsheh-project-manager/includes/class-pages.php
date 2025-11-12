<?php
if (!defined('ABSPATH')) exit;

class Hamnaghsheh_Pages {

  public function __construct() {
    add_action('admin_post_hamnaghsheh_premium_page', [$this, 'render_premium_page']);
    add_action('admin_post_nopriv_hamnaghsheh_premium_page', [$this, 'render_premium_page']);
  }

  public static function register_page() {
    add_rewrite_rule(
      '^hamnaghsheh-premium/?$',
      'index.php?hamnaghsheh_premium_page=1',
      'top'
    );
    add_rewrite_tag('%hamnaghsheh_premium_page%', '1');
  }

  public static function handle_template($template) {
    if (get_query_var('hamnaghsheh_premium_page') == 1) {
      ob_start();
      self::premium_page_content();
      return self::render_output();
    }
    return $template;
  }

  public static function premium_page_content() {
    echo "<div class='wrap'>";
    echo "<h1>صفحه خرید اشتراک</h1>";
    echo "<p>در این صفحه بخش خرید پرمیوم قرار می‌گیرد.</p>";
    echo "</div>";
  }

  private static function render_output() {
    $output = ob_get_clean();
    status_header(200);
    echo $output;
    exit;
  }
}
