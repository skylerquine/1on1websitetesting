<?php
/**
 * Template Name: Dynamic Teacher Page
 */

/* -------------------------
   1) Parse ProfileId
-------------------------- */
$slug = get_query_var('teacher_slug');
$slug = sanitize_title($slug);

$profileId = null;
if (preg_match('/-(\d+)$/', $slug, $m)) {
  $profileId = (int) $m[1];
}

if (!$profileId) {
  status_header(404);
  get_header();
  ?>
  <main class="teachers-page">
    <div class="teacher-page">
      <div class="teacher-page__container">
        <div class="teachers-directory__alert teachers-directory__alert--error">
          Teacher not found.
        </div>
      </div>
    </div>
  </main>
  <?php
  get_footer();
  exit;
}

/* -------------------------
   2) Try API
-------------------------- */
$endpoint = 'https://api.v1.app.1on1piano.com/public/teachers/' . $profileId;

$response = wp_remote_get($endpoint, [
  'timeout' => 8,
  'headers' => ['Accept' => 'application/json'],
]);

$data = null;

if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
  $data = json_decode(wp_remote_retrieve_body($response), true);
}

/* -------------------------
   3) MOCK DATA FALLBACK
-------------------------- */
if (!$data) {
  $data = [
    'profile' => [
      'displayName' => 'PianoDaddy12',
      'bio'         => 'This is mock bio content used while waiting for API authorization.',
      'quote'       => 'Music is a language everyone can speak.',
      'imageUrl'    => '',
    ],
    'matchPreference' => [
      'Tags' => [
        ['category' => 'Music Style', 'name' => 'Classical'],
        ['category' => 'Music Style', 'name' => 'Pop/Rock'],
        ['category' => 'Skill Level', 'name' => 'Beginner'],
        ['category' => 'Skill Level', 'name' => 'Intermediate'],
        ['category' => 'Personality', 'name' => 'Flexible'],
      ],
    ],
  ];
}

/* -------------------------
   4) Normalize data
-------------------------- */
$profile = $data['profile'] ?? [];
$tags    = $data['matchPreference']['Tags'] ?? [];

$displayNameRaw = $profile['displayName'] ?? 'Teacher';
$bioRaw         = $profile['bio'] ?? '';
$quoteRaw       = $profile['quote'] ?? '';
$imageUrlRaw    = isset($profile['imageUrl']) ? trim((string) $profile['imageUrl']) : '';

$displayName = esc_html($displayNameRaw);
$bio         = wpautop(esc_html($bioRaw));
$quote       = esc_html($quoteRaw);

$placeholder_image = 'https://1on1piano.com/wp-content/uploads/2026/03/teacher-placeholder.png';
$imageUrl          = $imageUrlRaw !== '' ? esc_url($imageUrlRaw) : $placeholder_image;

$tagsByCategory = [];
foreach ($tags as $t) {
  if (!isset($t['category'], $t['name'])) continue;
  $category = (string) $t['category'];
  $name     = (string) $t['name'];
  if ($category === '' || $name === '') continue;
  $tagsByCategory[$category][] = $name;
}

/* -------------------------
   5) Build SEO content
-------------------------- */
// Join an array as a natural list: "A, B and C"
$natural_list = static function (array $items): string {
  if (count($items) === 1) return $items[0];
  $last = array_pop($items);
  return implode(', ', $items) . ' and ' . $last;
};

$seo_parts = [];
if (!empty($tagsByCategory['Music Style'])) {
  $seo_parts[] = 'specializing in ' . $natural_list($tagsByCategory['Music Style']) . ' piano';
}
if (!empty($tagsByCategory['Skill Level'])) {
  $seo_parts[] = 'welcoming ' . $natural_list($tagsByCategory['Skill Level']) . ' students';
}
if (!empty($tagsByCategory['Age Group'])) {
  $seo_parts[] = 'working with ' . $natural_list($tagsByCategory['Age Group']) . ' learners';
}
if (!empty($tagsByCategory['Personality'])) {
  $personality_items = array_map('strtolower', $tagsByCategory['Personality']);
  $seo_parts[] = 'bringing a ' . $natural_list($personality_items) . ' teaching approach';
}

$seo_auto_para = '';
if (!empty($seo_parts)) {
  $seo_auto_para = $displayNameRaw . ' is an online piano teacher on 1ON1 Piano, '
                 . implode(', ', $seo_parts) . '.';
}

// Meta description: prefer bio (trimmed), fall back to auto-paragraph
$raw_meta     = $bioRaw !== '' ? wp_strip_all_tags($bioRaw) : $seo_auto_para;
$meta_desc    = mb_strlen($raw_meta) > 155 ? mb_substr($raw_meta, 0, 152) . '...' : $raw_meta;

// Shared values reused in wp_head and schema
$og_image = $imageUrlRaw !== '' ? $imageUrlRaw : $placeholder_image;
$page_url = home_url('/teachers/' . $slug . '/');
$og_title = 'Online Piano Lessons with ' . $displayNameRaw;

// Register meta & OG tags into <head> — must be before get_header()
add_action('wp_head', function () use ($meta_desc, $og_title, $og_image, $page_url) {
  echo '<meta name="description"         content="' . esc_attr($meta_desc)  . '">' . "\n";
  echo '<meta property="og:type"         content="profile">'                        . "\n";
  echo '<meta property="og:title"        content="' . esc_attr($og_title)   . '">' . "\n";
  echo '<meta property="og:description"  content="' . esc_attr($meta_desc)  . '">' . "\n";
  echo '<meta property="og:image"        content="' . esc_url($og_image)    . '">' . "\n";
  echo '<meta property="og:url"          content="' . esc_url($page_url)    . '">' . "\n";
}, 5);

get_header();
?>

<main class="teachers-page">
  <div class="teacher-page">
    <div class="teacher-page__container">

    <p class="teacher-page__back-link-wrap">
      <a href="<?php echo esc_url(home_url('/teachers/')); ?>" class="teacher-page__back-link">← Back to all teachers</a>
    </p>

    <section class="teacher-card teacher-page__hero">
      <div class="teacher-page__hero-inner">
        <div class="teacher-page__image-col">
          <div class="teacher-page__image-wrap">
            <img
              src="<?php echo esc_url($imageUrl); ?>"
              alt="Online piano teacher <?php echo esc_attr($displayNameRaw); ?> — profile photo"
              class="teacher-page__image"
              loading="lazy"
              onerror="this.onerror=null;this.src='<?php echo esc_url($placeholder_image); ?>';"
            />
          </div>
        </div>

        <div class="teacher-page__content-col">
          <h1 class="teacher-page__title">Online Piano Lessons with <?php echo $displayName; ?></h1>

          <?php if ($quoteRaw !== ''): ?>
            <blockquote class="teacher-page__quote">
              <?php echo $quote; ?>
            </blockquote>
          <?php endif; ?>

          <?php if ($seo_auto_para !== ''): ?>
            <p class="teacher-page__seo-para"><?php echo esc_html($seo_auto_para); ?></p>
          <?php endif; ?>

          <a href="https://app.1on1piano.com/match" class="teachers-filters__submit teacher-page__cta" target="_blank" rel="noopener noreferrer">
            Request a Lesson with <?php echo $displayName; ?>
          </a>
        </div>
      </div>
    </section>

    <?php if ($bioRaw !== ''): ?>
      <section class="teacher-page__section">
        <h2 class="teacher-page__section-title">About <?php echo $displayName; ?></h2>
        <div class="teacher-page__bio">
          <?php echo $bio; ?>
        </div>
      </section>
    <?php endif; ?>

    <?php if (!empty($tagsByCategory)): ?>
      <section class="teacher-page__section">
        <h2 class="teacher-page__section-title">Teaching Specialties</h2>

        <div class="teacher-page__tags-grid">
          <?php foreach ($tagsByCategory as $category => $items): ?>
            <?php if (empty($items)) continue; ?>
            <div class="teacher-page__tag-group">
              <h3 class="teacher-page__tag-group-title"><?php echo esc_html($category); ?></h3>

              <div class="teacher-page__chips">
                <?php foreach ($items as $item): ?>
                  <span class="teacher-page__chip"><?php echo esc_html($item); ?></span>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    </div>
  </div>
</main>

<script type="application/ld+json">
<?php
$schema = [
  '@context'    => 'https://schema.org',
  '@type'       => 'Person',
  'name'        => $displayNameRaw,
  'description' => wp_strip_all_tags($bioRaw !== '' ? $bioRaw : $seo_auto_para),
  'jobTitle'    => 'Piano Teacher',
  'url'         => $page_url,
  'image'       => $og_image,
];

if (!empty($tagsByCategory['Music Style'])) {
  $schema['knowsAbout'] = $tagsByCategory['Music Style'];
}

if (!empty($tagsByCategory['Skill Level'])) {
  $schema['audience'] = [
    '@type'           => 'EducationalAudience',
    'educationalRole' => implode(', ', $tagsByCategory['Skill Level']),
  ];
}

echo wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>
</script>

<?php get_footer(); ?>