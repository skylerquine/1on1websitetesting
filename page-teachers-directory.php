<?php
/**
 * Template Name: Teachers Directory
 * URL: /teachers/
 */

get_header();

$api_base = 'https://api.v1.app.1on1piano.com';
$placeholder_image = 'https://1on1piano.com/wp-content/uploads/2026/03/teacher-placeholder.png';

$tag_endpoints = [
  'age-group'   => '/age-group/',
  'music-style' => '/music-style/',
  'personality' => '/personality/',
  'skill-level' => '/skill-level/',
];

$tag_labels = [
  'age-group'   => 'Age Group',
  'music-style' => 'Music Style',
  'personality' => 'Personality',
  'skill-level' => 'Skill Level',
];

$per_page = 24;
$page = (int) get_query_var('tpage');
if ($page < 1) {
  $page = 1;
}

$offset = ($page - 1) * $per_page;
$count  = $per_page;

/**
 * Read selected tag ids from query string.
 * Supports:
 *   ?tag_ids[]=1&tag_ids[]=2
 *   ?tag_ids=1&tag_ids=2
 */
$selected_tag_ids = [];
if (isset($_GET['tag_ids'])) {
  $raw_tag_ids = $_GET['tag_ids'];

  if (!is_array($raw_tag_ids)) {
    $raw_tag_ids = [$raw_tag_ids];
  }

  $selected_tag_ids = array_values(array_unique(array_filter(array_map(
    static fn($v) => absint($v),
    $raw_tag_ids
  ))));
}

/**
 * Helper: build URL with array params preserved.
 */
function build_url_with_query(string $base_url, array $params): string {
  $parts = [];

  foreach ($params as $key => $value) {
    if (is_array($value)) {
      foreach ($value as $item) {
        $parts[] = rawurlencode($key) . '=' . rawurlencode((string) $item);
      }
    } else {
      $parts[] = rawurlencode($key) . '=' . rawurlencode((string) $value);
    }
  }

  return $parts ? $base_url . '?' . implode('&', $parts) : $base_url;
}

/**
 * Load all tags for each category.
 */
$all_tags_by_category = [];
$tag_load_errors = [];

foreach ($tag_endpoints as $category_slug => $endpoint_path) {
  $endpoint_url = rtrim($api_base, '/') . $endpoint_path;

  $resp = wp_remote_get($endpoint_url, [
    'timeout' => 10,
    'headers' => [
      'Accept' => 'application/json',
    ],
  ]);

  if (is_wp_error($resp)) {
    $tag_load_errors[] = $category_slug . ': ' . $resp->get_error_message();
    $all_tags_by_category[$category_slug] = [];
    continue;
  }

  $status = wp_remote_retrieve_response_code($resp);
  $body   = wp_remote_retrieve_body($resp);

  if ($status < 200 || $status >= 300) {
    $tag_load_errors[] = $category_slug . ': HTTP ' . $status;
    $all_tags_by_category[$category_slug] = [];
    continue;
  }

  $data = json_decode($body, true);
  $all_tags_by_category[$category_slug] = is_array($data) ? $data : [];
}

/**
 * Build /public/teachers request with pagination + selected tag ids.
 */
$teachers_query = [
  'offset' => $offset,
  'count'  => $count,
];

if (!empty($selected_tag_ids)) {
  $teachers_query['tag_ids'] = $selected_tag_ids;
}

$request_url = build_url_with_query(
  rtrim($api_base, '/') . '/public/teachers',
  $teachers_query
);

$response = wp_remote_get($request_url, [
  'timeout' => 10,
  'headers' => [
    'Accept' => 'application/json',
  ],
]);

$items = [];
$total_pages = 1;
$total_results = 0;
$error_message = '';

if (is_wp_error($response)) {
  $error_message = $response->get_error_message();
} else {
  $status = wp_remote_retrieve_response_code($response);
  $body   = wp_remote_retrieve_body($response);

  if ($status >= 200 && $status < 300) {
    $data = json_decode($body, true);

    if (is_array($data)) {
      $items = $data['results'] ?? [];
      $total_pages = (int) ($data['totalPages'] ?? 1);
      $total_results = (int) ($data['totalResults'] ?? 0);
    }
  } else {
    $error_message = 'Backend returned HTTP ' . $status;
  }
}

/**
 * Preserve selected filters in pagination URLs.
 */
function teachers_directory_page_url(int $page, array $selected_tag_ids): string {
  $base = home_url('/teachers/');
  $params = ['tpage' => $page];

  if (!empty($selected_tag_ids)) {
    $params['tag_ids'] = $selected_tag_ids;
  }

  return build_url_with_query($base, $params);
}
?>

<main class="teachers-page">
  <div class="teachers-directory">
    <header class="teachers-directory__header">
      <h1 class="teachers-directory__title">Online Piano Teachers</h1>
      <p class="teachers-directory__intro">
        Browse teachers on 1ON1 Piano and filter by age group, music style, personality, and skill level.
      </p>
      <?php if (!empty($total_results)): ?>
        <p class="teachers-directory__count">
          <?php echo esc_html(number_format_i18n($total_results)); ?> teachers
        </p>
      <?php endif; ?>
    </header>

    <?php if (!empty($error_message)): ?>
      <div class="teachers-directory__alert teachers-directory__alert--error">
        <?php echo esc_html($error_message); ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($tag_load_errors)): ?>
      <div class="teachers-directory__alert teachers-directory__alert--warning">
        <strong>Some filters could not be loaded:</strong><br>
        <?php echo esc_html(implode(' | ', $tag_load_errors)); ?>
      </div>
    <?php endif; ?>

    <form method="get" action="<?php echo esc_url(home_url('/teachers/')); ?>" class="teachers-filters">
      <div class="teachers-filters__grid">
        <?php foreach ($tag_endpoints as $category_slug => $_unused): ?>
          <?php $tags = $all_tags_by_category[$category_slug] ?? []; ?>

          <fieldset class="teachers-filters__group">
            <legend class="teachers-filters__legend">
              <?php echo esc_html($tag_labels[$category_slug] ?? $category_slug); ?>
            </legend>

            <?php if (!empty($tags)): ?>
              <div class="teachers-filters__options">
                <?php foreach ($tags as $tag): ?>
                  <?php
                    $tag_id = isset($tag['id']) ? absint($tag['id']) : 0;
                    $tag_name = isset($tag['name']) ? (string) $tag['name'] : '';
                    if (!$tag_id || $tag_name === '') {
                      continue;
                    }

                    $input_id = 'tag-' . sanitize_html_class($category_slug) . '-' . $tag_id;
                  ?>

                  <label for="<?php echo esc_attr($input_id); ?>" class="teachers-filters__option">
                    <input
                      id="<?php echo esc_attr($input_id); ?>"
                      type="checkbox"
                      name="tag_ids[]"
                      value="<?php echo esc_attr($tag_id); ?>"
                      <?php checked(in_array($tag_id, $selected_tag_ids, true)); ?>
                      class="teachers-filters__checkbox"
                    />

                    <span class="teachers-filters__label-text">
                      <?php echo esc_html($tag_name); ?>
                    </span>
                  </label>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <p class="teachers-filters__empty">No tags available.</p>
            <?php endif; ?>
          </fieldset>
        <?php endforeach; ?>
      </div>

      <div class="teachers-filters__actions">
        <button type="submit" class="teachers-filters__submit">
          Apply Filters
        </button>

        <a href="<?php echo esc_url(home_url('/teachers/')); ?>" class="teachers-filters__clear">
          Clear Filters
        </a>

        <?php if (!empty($selected_tag_ids)): ?>
          <span class="teachers-filters__selected-count">
            <?php echo esc_html(count($selected_tag_ids)); ?> tag(s) selected
          </span>
        <?php endif; ?>
      </div>
    </form>

    <section
      class="teachers-directory__grid"
      id="teachers-grid"
      data-current-page="<?php echo esc_attr($page); ?>"
      data-total-pages="<?php echo esc_attr($total_pages); ?>"
    >
      <?php foreach ($items as $t): ?>
        <?php
          $slug = isset($t['slug']) ? (string) $t['slug'] : '';
          $name = $t['displayName'] ?? 'Teacher';
          $headline = $t['headline'] ?? '';
          $raw_image_url = isset($t['imageUrl']) ? trim((string) $t['imageUrl']) : '';
          $image_url = $raw_image_url !== '' ? $raw_image_url : $placeholder_image;
          $profile_url = home_url('/teachers/' . rawurlencode($slug) . '/');
          $profile_tags = $t['matchPreference']['Tags'] ?? [];
          $tagsByCategory = [];
          foreach ($profile_tags as $profile_tag) {
            if (!isset($profile_tag['category'], $profile_tag['name'])) continue;
            $category = (string) $profile_tag['category'];
            $profile_tag_name = (string) $profile_tag['name'];
            if ($category === '' || $profile_tag_name === '') continue;
            $tagsByCategory[$category][] = $profile_tag_name;
          }
        ?>

        <article class="teacher-card">
          <a href="<?php echo esc_url($profile_url); ?>" class="teacher-card__link">
            <div class="teacher-card__image-wrap">
              <img
                src="<?php echo esc_url($image_url); ?>"
                alt="<?php echo esc_attr($name); ?>"
                loading="lazy"
                referrerpolicy="no-referrer"
                class="teacher-card__image"
                onerror="this.onerror=null;this.src='<?php echo esc_url($placeholder_image); ?>';"
              />
            </div>

            <div class="teacher-card__content">
              <h2 class="teacher-card__name"><?php echo esc_html($name); ?></h2>

              <?php if ($headline !== ''): ?>
                <p class="teacher-card__headline"><?php echo esc_html($headline); ?></p>
              <?php endif; ?>

              <?php if (!empty($tagsByCategory)): ?>
                <div class="teacher-card__tags-grid">
                  <?php foreach ($tagsByCategory as $category => $items): ?>
                    <?php if (empty($items)) continue; ?>
                    <div class="teacher-card__tag-group">
                      <h6 class="teacher-card__tag-group-title"><?php echo esc_html($category); ?></h6>

                      <div class="teacher-card__chips">
                        <?php foreach ($items as $item): ?>
                          <span class="teacher-card__chip"><?php echo esc_html($item); ?></span>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </a>
        </article>
      <?php endforeach; ?>

      <?php if (empty($items) && empty($error_message)): ?>
        <p class="teachers-directory__empty">No teachers found.</p>
      <?php endif; ?>
    </section>

    <?php if ($total_pages > 1): ?>
      <div id="teachers-load-trigger" class="teachers-load-trigger" aria-hidden="true"></div>
      <div id="teachers-loading" class="teachers-loading" hidden>Loading more teachers...</div>

      <noscript>
        <nav class="teachers-directory__pagination">
          <?php if ($page > 1): ?>
            <a href="<?php echo esc_url(teachers_directory_page_url($page - 1, $selected_tag_ids)); ?>">← Prev</a>
          <?php endif; ?>

          <span>
            Page <?php echo esc_html($page); ?> of <?php echo esc_html($total_pages); ?>
          </span>

          <?php if ($page < $total_pages): ?>
            <a href="<?php echo esc_url(teachers_directory_page_url($page + 1, $selected_tag_ids)); ?>">Next →</a>
          <?php endif; ?>
        </nav>
      </noscript>
    <?php endif; ?>
  </div>
</main>

<script type="application/ld+json">
<?php
$schema_items = [];
$position     = 1;
foreach ($items as $t) {
  $t_slug = isset($t['slug']) ? (string) $t['slug'] : '';
  $t_name = isset($t['displayName']) ? (string) $t['displayName'] : 'Teacher';
  if ($t_slug === '') continue;
  $schema_items[] = [
    '@type'    => 'ListItem',
    'position' => $position++,
    'url'      => home_url('/teachers/' . rawurlencode($t_slug) . '/'),
    'name'     => $t_name,
  ];
}
$schema = [
  '@context'        => 'https://schema.org',
  '@type'           => 'ItemList',
  'name'            => 'Online Piano Teachers',
  'url'             => home_url('/teachers/'),
  'itemListElement' => $schema_items,
];
echo wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const grid = document.getElementById('teachers-grid');
  const trigger = document.getElementById('teachers-load-trigger');
  const loading = document.getElementById('teachers-loading');

  if (!grid || !trigger) return;

  let currentPage = parseInt(grid.dataset.currentPage || '1', 10);
  const totalPages = parseInt(grid.dataset.totalPages || '1', 10);
  let isLoading = false;

  if (currentPage >= totalPages) return;

  const observer = new IntersectionObserver(async (entries) => {
    const entry = entries[0];
    if (!entry.isIntersecting || isLoading) return;
    if (currentPage >= totalPages) return;

    isLoading = true;
    if (loading) {
      loading.textContent = 'Loading more teachers...';
      loading.hidden = false;
    }

    try {
      const nextPage = currentPage + 1;
      const url = new URL(window.location.href);
      url.searchParams.set('tpage', nextPage);

      const response = await fetch(url.toString(), {
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      });

      if (!response.ok) {
        throw new Error('HTTP ' + response.status);
      }

      const html = await response.text();
      const parser = new DOMParser();
      const doc = parser.parseFromString(html, 'text/html');
      const newGrid = doc.getElementById('teachers-grid');

      if (!newGrid) {
        throw new Error('Missing teachers grid in response');
      }

      Array.from(newGrid.children).forEach((child) => {
        if (child.classList && child.classList.contains('teachers-directory__empty')) {
          return;
        }
        grid.appendChild(child);
      });

      currentPage = nextPage;
      grid.dataset.currentPage = String(currentPage);

      if (currentPage >= totalPages) {
        observer.disconnect();
        if (trigger) trigger.remove();
        if (loading) loading.remove();
      } else if (loading) {
        loading.hidden = true;
      }
    } catch (error) {
      console.error('Infinite scroll failed:', error);
      observer.disconnect();
      if (loading) {
        loading.textContent = 'Unable to load more teachers.';
        loading.hidden = false;
      }
    } finally {
      isLoading = false;
    }
  }, {
    rootMargin: '300px 0px'
  });

  observer.observe(trigger);
});
</script>

<?php get_footer(); ?>