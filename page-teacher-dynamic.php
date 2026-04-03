<?php
/**
 * Template Name: Dynamic Teacher Page
 */

get_header();
<?php
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
  ?>
  <main class="teacher-page">
    <div class="teacher-page__container">
      <div class="teachers-directory__alert teachers-directory__alert--error">
        Teacher not found.
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
$endpoint = 'https://api.v1.dev.app.1on1piano.com/public/teachers/' . $profileId;

$response = wp_remote_get($endpoint, [
  'timeout' => 8,
  'headers' => [
    'Accept' => 'application/json',
  ],
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
      'bio' => 'This is mock bio content used while waiting for API authorization.',
      'quote' => 'Music is a language everyone can speak.',
      'imageUrl' => '',
    ],
    'matchPreference' => [
      'Tags' => [
        ['category' => 'Music Style', 'name' => 'Classical'],
        ['category' => 'Music Style', 'name' => 'Pop/Rock'],
        ['category' => 'Skill Level', 'name' => 'Beginner'],
        ['category' => 'Skill Level', 'name' => 'Intermediate'],
        ['category' => 'Personality', 'name' => 'Flexible'],
      ]
    ]
  ];
}

/* -------------------------
   4) Normalize data
-------------------------- */
$profile = $data['profile'] ?? [];
$tags = $data['matchPreference']['Tags'] ?? [];

$displayNameRaw = $profile['displayName'] ?? 'Teacher';
$bioRaw = $profile['bio'] ?? '';
$quoteRaw = $profile['quote'] ?? '';
$imageUrlRaw = isset($profile['imageUrl']) ? trim((string) $profile['imageUrl']) : '';

$displayName = esc_html($displayNameRaw);
$bio = wpautop(esc_html($bioRaw));
$quote = esc_html($quoteRaw);

$placeholder_image = 'https://1on1piano.com/wp-content/uploads/2026/03/teacher-placeholder.png';
$imageUrl = $imageUrlRaw !== '' ? esc_url($imageUrlRaw) : $placeholder_image;

$tagsByCategory = [];
foreach ($tags as $t) {
  if (!isset($t['category'], $t['name'])) {
    continue;
  }
  $category = (string) $t['category'];
  $name = (string) $t['name'];
  if ($category === '' || $name === '') {
    continue;
  }
  $tagsByCategory[$category][] = $name;
}
?>

<main class="teachers-page">
  <div class="teachers-hero" style="height: 120px; background: red;"></div>

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
              alt="<?php echo esc_attr($displayNameRaw); ?>"
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
        <h2 class="teacher-page__section-title">Match Tags</h2>

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
  "@context" => "https://schema.org",
  "@type" => "Person",
  "name" => $displayNameRaw,
  "description" => $bioRaw,
  "jobTitle" => "Piano Teacher",
  "url" => home_url('/teachers/' . $slug . '/'),
];

if ($imageUrlRaw !== '') {
  $schema["image"] = $imageUrlRaw;
} else {
  $schema["image"] = $placeholder_image;
}

echo wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>
</script>

<?php get_footer(); ?>