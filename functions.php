<?php
/**
 * Astra Child Theme functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package Astra Child
 * @since 1.0.0
 */

/**
 * Define Constants
 */
define( 'CHILD_THEME_ASTRA_CHILD_VERSION', '1.0.3' );

/**
 * Enqueue styles
 */
function child_enqueue_styles() {

	wp_enqueue_style( 'astra-child-theme-css', get_stylesheet_directory_uri() . '/style.css', array('astra-theme-css'), CHILD_THEME_ASTRA_CHILD_VERSION, 'all' );

}

add_action( 'wp_enqueue_scripts', 'child_enqueue_styles', 15 );
/**
 * Dynamic teacher pages: /teachers/{slug}
 */


add_action('init', function () {
  add_rewrite_rule(
    '^teachers/([^/]+)/?$',
    'index.php?teacher_slug=$matches[1]',
    'top'
  );
});

add_filter('query_vars', function ($vars) {
  $vars[] = 'teacher_slug';
  $vars[] = 'tpage'; // directory pagination — was in commented-out block, causing duplicate-scroll bug
  return $vars;
});

add_filter('template_include', function ($template) {
  $slug = get_query_var('teacher_slug');
  if (!empty($slug)) {
    $custom = get_stylesheet_directory() . '/page-teacher-dynamic.php';
    if (file_exists($custom)) return $custom;
  }
  return $template;
});

add_filter('document_title_parts', function ($title_parts) {
  $slug = get_query_var('teacher_slug');
  if (!empty($slug)) {
    // Strip trailing "-123" for nicer titles
    $base = preg_replace('/-\d+$/', '', $slug);
    $base = ucwords(str_replace('-', ' ', $base));

    $title_parts['title'] = "Online Piano Lessons with {$base}";
  }
  return $title_parts;
});

// Full-width layout for teacher profile pages:
// - ast-no-sidebar removes Astra's sidebar so #primary gets 100% width
// - teacher-full-width is our hook for CSS to override .ast-container max-width
add_filter('body_class', function ($classes) {
  if (!empty(get_query_var('teacher_slug'))) {
    $classes[] = 'ast-no-sidebar';
    $classes[] = 'teacher-full-width';
  }
  return $classes;
});

/**
 * Teacher directory: /teachers/
 */
/**add_action('init', function () {
  add_rewrite_rule(
    '^teachers/?$',
    'index.php?teachers_directory=1',
    'top'
  );
});

add_filter('query_vars', function ($vars) {
  $vars[] = 'teachers_directory';
  $vars[] = 'tpage'; // optional pagination
  return $vars;
});

add_filter('template_include', function ($template) {
  if ((int) get_query_var('teachers_directory') === 1) {
    $custom = get_stylesheet_directory() . '/page-teachers-directory.php';
    if (file_exists($custom)) return $custom;
  }
  return $template;
});
*/
// Header clearance for full-bleed teacher pages.
// Sets --masthead-bottom on :root so CSS can apply it as margin-top on the
// non-transformed child elements (.teachers-directory, .teacher-page).
// Avoids setting inline padding on the transform element itself, which gets
// clipped by Astra's overflow:hidden on .ast-container.
add_action('wp_footer', function () {
  ?>
  <script>
  (function () {
    if (!document.querySelector('.teachers-page')) return;
    function adjust() {
      var m = document.getElementById('masthead');
      if (!m) return;
      var bottom = m.getBoundingClientRect().bottom;
      document.documentElement.style.setProperty(
        '--masthead-bottom', Math.max(0, bottom) + 'px'
      );
    }
    adjust();
    requestAnimationFrame(adjust);
    window.addEventListener('load', adjust);
    window.addEventListener('resize', adjust);
  })();
  </script>
  <?php
});

if (!function_exists('oai_fetch_json_cached')) {
  function oai_fetch_json_cached($cache_key, $url, $ttl_seconds = 900) {
    $cached = get_transient($cache_key);
    if ($cached !== false) return $cached;

    $resp = wp_remote_get($url, [
      'timeout' => 10,
      'headers' => ['Accept' => 'application/json'],
    ]);

    if (is_wp_error($resp)) return null;

    $code = wp_remote_retrieve_response_code($resp);
    $body = wp_remote_retrieve_body($resp);

    if ($code !== 200 || empty($body)) return null;

    $json = json_decode($body, true);
    if (!is_array($json)) return null;

    set_transient($cache_key, $json, $ttl_seconds);
    return $json;
  }
}