<?php
declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/core/helpers.php';


/* =========================================================
   HELPERS
========================================================= */

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars(
            (string)($value ?? ''),
            ENT_QUOTES,
            'UTF-8'
        );
    }
}


if (!function_exists('dbRows')) {
    function dbRows(mysqli $conn, string $sql): array
    {
        $rows = [];

        try {
            $res = $conn->query($sql);

            if ($res instanceof mysqli_result) {
                while ($row = $res->fetch_assoc()) {
                    $rows[] = $row;
                }

                $res->free();
            }
        } catch (Throwable $e) {
            error_log('dbRows error: ' . $e->getMessage());
        }

        return $rows;
    }
}


if (!function_exists('firstRow')) {
    function firstRow(
        mysqli $conn,
        string $sql,
        array $default = []
    ): array {
        $rows = dbRows($conn, $sql);

        return !empty($rows)
            ? array_merge($default, $rows[0])
            : $default;
    }
}


if (!function_exists('tableExists')) {
    function tableExists(
        mysqli $conn,
        string $table
    ): bool {
        try {
            $table = $conn->real_escape_string($table);

            $res = $conn->query(
                "SHOW TABLES LIKE '{$table}'"
            );

            return $res &&
                $res instanceof mysqli_result &&
                $res->num_rows > 0;

        } catch (Throwable $e) {
            return false;
        }
    }
}


if (!function_exists('assetUrl')) {
    function assetUrl(
        $path,
        string $defaultPrefix = ''
    ): string {
        $path = trim((string)$path);

        if ($path === '') {
            return '';
        }

        $path = str_replace('\\', '/', $path);

        /*
         * External / absolute URL.
         */
        if (
            preg_match(
                '#^(https?:)?//#i',
                $path
            )
        ) {
            return $path;
        }

        /*
         * Absolute local path.
         */
        if (str_starts_with($path, '/')) {
            return $path;
        }

        $path = ltrim($path, '/');

        /*
         * Already has a known directory.
         */
        if (
            str_starts_with($path, 'uploads/') ||
            str_starts_with($path, 'assets/') ||
            str_starts_with($path, 'dashboard/uploads/')
        ) {
            return $path;
        }

        return $defaultPrefix !== ''
            ? trim($defaultPrefix, '/') . '/' . $path
            : $path;
    }
}


if (!function_exists('sectionBgStyle')) {
    function sectionBgStyle(
        array $settings,
        string $fallback = '#ffffff'
    ): string {
        $type = strtolower(
            trim(
                (string)($settings['bg_type'] ?? 'color')
            )
        );

        $color = trim(
            (string)(
                $settings['bg_color'] ??
                $fallback
            )
        );

        $image = assetUrl(
            $settings['bg_image'] ?? ''
        );

        $overlay = trim(
            (string)(
                $settings['overlay_color'] ??
                'rgba(11,44,58,.55)'
            )
        );

        if ($color === '') {
            $color = $fallback;
        }

        if ($overlay === '') {
            $overlay = 'rgba(11,44,58,.55)';
        }

        if (
            $type === 'image' &&
            $image !== ''
        ) {
            return "
                background-image:
                    linear-gradient(
                        {$overlay},
                        {$overlay}
                    ),
                    url('" . h($image) . "');

                background-position:center;
                background-size:cover;
                background-repeat:no-repeat;
            ";
        }

        return 'background-color:' .
            h($color) .
            ';';
    }
}


if (!function_exists('sectionVideo')) {
    function sectionVideo(
        array $settings
    ): string {
        $type = strtolower(
            trim(
                (string)(
                    $settings['bg_type'] ??
                    'color'
                )
            )
        );

        return $type === 'video'
            ? assetUrl(
                $settings['bg_video'] ?? ''
            )
            : '';
    }
}


if (!function_exists('hasVisualBg')) {
    function hasVisualBg(
        array $settings
    ): bool {
        $type = strtolower(
            trim(
                (string)(
                    $settings['bg_type'] ??
                    'color'
                )
            )
        );

        return (
            $type === 'image' &&
            !empty($settings['bg_image'])
        ) || (
            $type === 'video' &&
            !empty($settings['bg_video'])
        );
    }
}


/*
|--------------------------------------------------------------------------
| Short text helper
|--------------------------------------------------------------------------
|
| Keeps homepage programme cards short.
|
*/
if (!function_exists('shortWords')) {
    function shortWords(
        string $text,
        int $limit = 22
    ): string {
        $text = html_entity_decode(
            strip_tags($text),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        $text = trim(
            preg_replace(
                '/\s+/u',
                ' ',
                $text
            )
        );

        if ($text === '') {
            return '';
        }

        $words = preg_split(
            '/\s+/u',
            $text,
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        if (!$words) {
            return '';
        }

        if (count($words) <= $limit) {
            return $text;
        }

        return implode(
            ' ',
            array_slice(
                $words,
                0,
                $limit
            )
        ) . '...';
    }
}


/*
|--------------------------------------------------------------------------
| Merge programme database content with defaults
|--------------------------------------------------------------------------
|
| Empty DB values will not destroy useful fallback values.
|
*/
if (!function_exists('mergeProgramData')) {
    function mergeProgramData(
        array $default,
        array $database
    ): array {
        $merged = $default;

        foreach ($database as $key => $value) {

            /*
             * Keep database IDs and numeric fields,
             * including zero.
             */
            if (
                is_int($value) ||
                is_float($value)
            ) {
                $merged[$key] = $value;
                continue;
            }

            /*
             * For strings, only replace fallback
             * when database value is meaningful.
             */
            if (
                $value !== null &&
                trim((string)$value) !== ''
            ) {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }
}


/*
|--------------------------------------------------------------------------
| Programme image helper
|--------------------------------------------------------------------------
*/
if (!function_exists('programImage')) {
    function programImage(
        array $program
    ): string {

        $featured = trim(
            (string)(
                $program['featured_image'] ??
                ''
            )
        );

        if ($featured !== '') {

            /*
             * External image.
             */
            if (
                preg_match(
                    '#^(https?:)?//#i',
                    $featured
                )
            ) {
                return $featured;
            }

            /*
             * Already a complete local path.
             */
            if (
                str_starts_with(
                    $featured,
                    '/'
                )
            ) {
                return $featured;
            }

            if (
                str_starts_with(
                    $featured,
                    'uploads/'
                ) ||
                str_starts_with(
                    $featured,
                    'assets/'
                )
            ) {
                return $featured;
            }

            /*
             * Plain filename.
             */
            return 'uploads/programs/' .
                ltrim(
                    $featured,
                    '/'
                );
        }

        $fallback = trim(
            (string)(
                $program['_fallback_img'] ??
                ''
            )
        );

        if ($fallback !== '') {
            return assetUrl($fallback);
        }

        return 'assets/img/program-placeholder.jpg';
    }
}


/*
|--------------------------------------------------------------------------
| Programme URL helper
|--------------------------------------------------------------------------
*/
if (!function_exists('programLink')) {
    function programLink(
        array $program
    ): string {

        /*
         * Use specifically configured URL first.
         */
        if (
            !empty($program['link']) &&
            trim(
                (string)$program['link']
            ) !== ''
        ) {
            return trim(
                (string)$program['link']
            );
        }

        /*
         * Then use slug.
         */
        if (
            !empty($program['slug'])
        ) {
            return 'programme?slug=' .
                rawurlencode(
                    trim(
                        (string)$program['slug']
                    )
                );
        }

        return 'programmes.php';
    }
}


/* =========================================================
   SITE SETTINGS
========================================================= */

$site_name = 'WOMEN IN TECHNOLOGY UGANDA';

$siteRows = dbRows(
    $conn,
    "
        SELECT setting_value
        FROM site_settings
        WHERE setting_key = 'site_name'
        LIMIT 1
    "
);

if (!empty($siteRows)) {
    $site_name =
        $siteRows[0]['setting_value'] ??
        $site_name;
}


/* =========================================================
   HOME SETTINGS
========================================================= */

$home = [
    'hero_headline' =>
        'Women in Technology Uganda',

    'hero_subtext' =>
        'Building pathways to dignified work, entrepreneurship, leadership, agency, and economic opportunity for women and girls from underserved communities through technology.',

    'hero_text_align' =>
        'center',

    'hero_text_valign' =>
        'center',

    'hero_overlay_opacity' =>
        0.48,

    'primary_cta_text' =>
        'Our Programs',

    'primary_cta_link' =>
        'programmes.php',

    'secondary_cta_text' =>
        'Support Our Cause',

    'secondary_cta_link' =>
        'donate.php',

    'get_involved_text' =>
        'Get Involved',

    'get_involved_link' =>
        'get-involved.php',
];


$homeRows = dbRows(
    $conn,
    "
        SELECT *
        FROM home
        WHERE is_active = 1
        ORDER BY id DESC
        LIMIT 1
    "
);

if (!empty($homeRows)) {
    $home = array_merge(
        $home,
        $homeRows[0]
    );
}


$overlay = max(
    0,
    min(
        0.9,
        (float)(
            $home['hero_overlay_opacity'] ??
            0.48
        )
    )
);


$halign =
    $home['hero_text_align'] ??
    'center';

$valign =
    $home['hero_text_valign'] ??
    'center';


$justify = [
    'left'   => 'flex-start',
    'center' => 'center',
    'right'  => 'flex-end',
][$halign] ?? 'center';


/* =========================================================
   HERO SLIDERS
========================================================= */

$sliders = dbRows(
    $conn,
    "
        SELECT *
        FROM sliders
        WHERE is_active = 1
        ORDER BY sort_order ASC, id ASC
    "
);


/* =========================================================
   IMPACT STATISTICS
========================================================= */

$impact_stats = [
    [
        'stat_value' => '20,000+',
        'stat_label' =>
            'Women and girls reached',
    ],

    [
        'stat_value' => '56',
        'stat_label' =>
            'Districts across Uganda',
    ],

    [
        'stat_value' => '10,000+',
        'stat_label' =>
            'Women connected to dignified work and income opportunities',
    ],

    [
        'stat_value' => '1,000+',
        'stat_label' =>
            'Entrepreneurs and businesses supported',
    ],
];


/* =========================================================
   PROGRAMMES
========================================================= */

/*
|--------------------------------------------------------------------------
| Load unique published parent programmes from database
|--------------------------------------------------------------------------
|
| Only programmes where parent_id IS NULL are loaded.
| Duplicate slugs are prevented by selecting the lowest ID per slug.
|
*/
$databasePrograms = dbRows(
    $conn,
    "
        SELECT p.*
        FROM programmes p

        INNER JOIN (
            SELECT MIN(id) AS id
            FROM programmes
            WHERE status = 'published'
              AND parent_id IS NULL
            GROUP BY slug
        ) unique_programmes
            ON unique_programmes.id = p.id

        ORDER BY
            p.display_order ASC,
            p.id ASC
    "
);


/*
|--------------------------------------------------------------------------
| Homepage fallback programme definitions
|--------------------------------------------------------------------------
|
| These are only used when a matching programme is not available
| in the database.
|
*/
$homepageProgramDefaults = [

    [
        'title' => 'ElevateHer360',

        'summary' =>
            'Equipping young women from underserved communities with practical digital, employability, leadership and agency-building skills for work and income opportunities.',

        'description' =>
            'Equipping young women from underserved communities with practical digital, employability, leadership and agency-building skills for work and income opportunities.',

        'content' => '',

        'featured_image' => '',

        'slug' => 'elevateher360',

        'link' => 'programme?slug=elevateher360',

        '_fallback_img' =>
            '/assets/img/elevateher360.jpg',

        'display_order' => 1,
    ],


    [
        'title' => 'Elevate Accelerator',

        'summary' =>
            'Supporting women entrepreneurs and startups with business skills, mentorship, access to finance, networks and opportunities for sustainable business growth.',

        'description' =>
            'Supporting women entrepreneurs and startups with business skills, mentorship, access to finance, networks and opportunities for sustainable business growth.',

        'content' => '',

        'featured_image' => '',

        'slug' => 'elevate-accelerator',

        'link' => 'programme?slug=elevate-accelerator',

        '_fallback_img' =>
            '/assets/img/elevate-accelerator.jpg',

        'display_order' => 2,
    ],


    [
        'title' => 'Code Girls',

        'summary' =>
            'Code Girls ICT Clubs creates safe and supportive spaces for secondary school students, particularly girls, to build practical digital and coding skills.',

        'description' =>
            'Code Girls ICT Clubs is WITU’s school-based STEM initiative that creates safe and supportive spaces for secondary school students, particularly girls, to develop practical digital skills, learn coding, and use technology to solve problems.',

        'content' => '',

        'featured_image' => '',

        'slug' => 'code-girls',

        'link' => 'programme?slug=code-girls',

        '_fallback_img' =>
            '/assets/img/code-girls.jpg',

        'display_order' => 3,
    ],

];


$programs = [];

$addedProgramKeys = [];


foreach ($homepageProgramDefaults as $defaultProgram) {

    $matchedProgram = null;



    $defaultSlug = strtolower(
        trim(
            (string)(
                $defaultProgram['slug'] ?? ''
            )
        )
    );


    $defaultTitle = strtolower(
        trim(
            (string)(
                $defaultProgram['title'] ?? ''
            )
        )
    );


    /*
    |--------------------------------------------------------------------------
    | Search for matching database programme
    |--------------------------------------------------------------------------
    */

    foreach ($databasePrograms as $dbProgram) {

        $dbSlug = strtolower(
            trim(
                (string)(
                    $dbProgram['slug'] ?? ''
                )
            )
        );


        $dbTitle = strtolower(
            trim(
                (string)(
                    $dbProgram['title'] ?? ''
                )
            )
        );


        $slugMatches =
            $defaultSlug !== '' &&
            $dbSlug !== '' &&
            $defaultSlug === $dbSlug;


        $titleMatches =
            $defaultTitle !== '' &&
            $dbTitle !== '' &&
            $defaultTitle === $dbTitle;


        if ($slugMatches || $titleMatches) {

            /*
             * Merge DB record with fallback.
             *
             * If you already have mergeProgramData(),
             * use it here.
             */
            if (function_exists('mergeProgramData')) {

                $matchedProgram = mergeProgramData(
                    $defaultProgram,
                    $dbProgram
                );

            } else {

                /*
                 * Safe fallback merge.
                 */
                $matchedProgram = $defaultProgram;

                foreach ($dbProgram as $key => $value) {

                    if (
                        $value !== null &&
                        (
                            !is_string($value) ||
                            trim($value) !== ''
                        )
                    ) {
                        $matchedProgram[$key] = $value;
                    }
                }
            }

            break;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Use database version or fallback
    |--------------------------------------------------------------------------
    */

    $finalProgram =
        $matchedProgram ??
        $defaultProgram;


    /*
    |--------------------------------------------------------------------------
    | Prevent duplicate programme cards
    |--------------------------------------------------------------------------
    */

    $finalSlug = strtolower(
        trim(
            (string)(
                $finalProgram['slug'] ?? ''
            )
        )
    );


    $finalTitle = strtolower(
        trim(
            (string)(
                $finalProgram['title'] ?? ''
            )
        )
    );


    /*
     * Prefer slug as unique identifier.
     * Fall back to title when slug is missing.
     */
    $uniqueKey =
        $finalSlug !== ''
        ? 'slug:' . $finalSlug
        : 'title:' . $finalTitle;


    if (
        $uniqueKey !== 'title:' &&
        !isset($addedProgramKeys[$uniqueKey])
    ) {

        $programs[] =
            $finalProgram;


        $addedProgramKeys[$uniqueKey] =
            true;
    }
}



foreach ($databasePrograms as $dbProgram) {

    $dbSlug = strtolower(
        trim(
            (string)(
                $dbProgram['slug'] ?? ''
            )
        )
    );


    $dbTitle = strtolower(
        trim(
            (string)(
                $dbProgram['title'] ?? ''
            )
        )
    );


    $uniqueKey =
        $dbSlug !== ''
        ? 'slug:' . $dbSlug
        : 'title:' . $dbTitle;


    if (
        $uniqueKey !== 'title:' &&
        !isset($addedProgramKeys[$uniqueKey])
    ) {

        $programs[] =
            $dbProgram;


        $addedProgramKeys[$uniqueKey] =
            true;
    }
}


/*
|--------------------------------------------------------------------------
| Final sorting
|--------------------------------------------------------------------------
|
| Keep programmes ordered using the programmes.display_order column.
|
*/
usort(
    $programs,
    static function (
        array $a,
        array $b
    ): int {

        $orderA =
            (int)(
                $a['display_order'] ??
                999
            );


        $orderB =
            (int)(
                $b['display_order'] ??
                999
            );


        if ($orderA === $orderB) {

            return
                (int)(
                    $a['id'] ??
                    0
                )
                <=>
                (int)(
                    $b['id'] ??
                    0
                );
        }


        return
            $orderA
            <=>
            $orderB;
    }
);


/* =========================================================
   AGENCY
========================================================= */

$agency_cards = dbRows(
    $conn,
    "
        SELECT *
        FROM agency_cards
        WHERE is_active = 1
        ORDER BY sort_order ASC, id ASC
    "
);


if (empty($agency_cards)) {

    $agency_cards = [

        [
            'title' =>
                'Confidence',

            'icon' =>
                'fas fa-heart',

            'description' =>
                'Participants build self-belief, resilience, and motivation.',
        ],

        [
            'title' =>
                'Voice',

            'icon' =>
                'fas fa-comments',

            'description' =>
                'Participants strengthen communication, self-advocacy, and decision-making skills.',
        ],

        [
            'title' =>
                'Leadership',

            'icon' =>
                'fas fa-star',

            'description' =>
                'Participants develop leadership, responsibility, and problem-solving skills.',
        ],

    ];
}


/* =========================================================
   PARTICIPANT JOURNEY SETTINGS
========================================================= */

$journeySectionSettings = [

    'section_title' =>
        'The Participant Journey',

    'section_subtitle' =>
        'Young women move through a structured pathway that builds skills, work experience, and income. The journey combines practical training, real work exposure, and continuous professional support.',

    'bg_type' =>
        'color',

    'bg_color' =>
        '#ffffff',

    'bg_image' =>
        '',

    'bg_video' =>
        '',

    'overlay_color' =>
        'rgba(11,44,58,.55)',

    'is_active' =>
        1,
];


if (
    tableExists(
        $conn,
        'participant_journey_section_settings'
    )
) {
    $journeySectionSettings =
        firstRow(
            $conn,
            "
                SELECT *
                FROM participant_journey_section_settings
                WHERE is_active = 1
                ORDER BY id DESC
                LIMIT 1
            ",
            $journeySectionSettings
        );
}


/* =========================================================
   PARTICIPANT JOURNEY ITEMS
========================================================= */

$journeyRows = dbRows(
    $conn,
    "
        SELECT *
        FROM participant_journey
        WHERE is_active = 1
        ORDER BY
            sort_order ASC,
            is_continuous DESC,
            step_number ASC,
            id ASC
    "
);


$journeySteps = [
    1 => null,
    2 => null,
    3 => null,
    'continuous' => null,
];


foreach (
    $journeyRows
    as $journey
) {

    if (
        (int)(
            $journey['is_continuous'] ??
            0
        ) === 1
    ) {
        $journeySteps['continuous'] =
            $journey;

        continue;
    }


    $stepNumber = (int)(
        $journey['step_number'] ??
        0
    );


    if (
        in_array(
            $stepNumber,
            [1, 2, 3],
            true
        )
    ) {
        $journeySteps[$stepNumber] =
            $journey;
    }
}


$journeyDefaults = [

    1 => [
        'title' =>
            'Skills Training & Agency Building',

        'description' =>
            'Participants complete 3 to 6 months of intensive, market-led digital skills training while building confidence, leadership, self-advocacy, communication, and decision-making skills.',
    ],


    2 => [
        'title' =>
            'Work Experience & Exposure',

        'description' =>
            'Participants gain practical experience through internships, projects, mentorship, and exposure to employers and industry partners.',
    ],


    3 => [
        'title' =>
            'Economic Outcomes',

        'description' =>
            'Participants move into employment, entrepreneurship, freelancing, or further education pathways that increase income and long-term economic resilience.',
    ],


    'continuous' => [
        'title' =>
            'Continuous Mentorship & Career Support',

        'description' =>
            'Mentorship and career support run throughout the journey. Participants receive guidance from technical specialists, industry professionals, mentors, and alumni.',
    ],

];


/* =========================================================
   TESTIMONIALS
========================================================= */

$testimonials = dbRows(
    $conn,
    "
        SELECT *
        FROM testimonials
        WHERE is_active = 1
        ORDER BY sort_order ASC, id DESC
        LIMIT 12
    "
);


/* =========================================================
   PARTNER SETTINGS
========================================================= */

$partnerSectionSettings = [

    'page_title' =>
        'Our Partners',

    'page_subtitle' =>
        "Organizations supporting WITU's work and ecosystem",

    'bg_type' =>
        'color',

    'bg_color' =>
        '#ffffff',

    'bg_image' =>
        '',

    'bg_video' =>
        '',

    'overlay_color' =>
        'rgba(11,44,58,.55)',

    'is_active' =>
        1,
];


if (
    tableExists(
        $conn,
        'partner'
    )
) {
    $partnerSectionSettings =
        firstRow(
            $conn,
            "
                SELECT *
                FROM partner
                WHERE is_active = 1
                ORDER BY id DESC
                LIMIT 1
            ",
            $partnerSectionSettings
        );
}


/* =========================================================
   PARTNERS
========================================================= */

$partners = dbRows(
    $conn,
    "
        SELECT *
        FROM partners
        WHERE is_active = 1
        ORDER BY sort_order ASC, id DESC
    "
);


$page_title = 'Home';

include 'header.php';
?>




<!-- ======================================================
     HERO
======================================================= -->

<section class="witu-hero">

    <div
        class="witu-hero-slider"
        id="heroSlider"
    >

        <?php if (!empty($sliders)): ?>

            <?php
            foreach (
                $sliders
                as $i => $slider
            ):
            ?>

                <?php

                $background =
                    !empty(
                        $slider['image_path']
                    )
                    ? assetUrl(
                        $slider['image_path']
                    )
                    : '';


                $backgroundCss =
                    $background !== ''
                    ? "url('" .
                        h($background) .
                        "') center center / cover no-repeat"
                    : 'linear-gradient(135deg,#0b1b3a,#1a3a6e)';

                ?>

                <div
                    class="witu-hero-slide <?php echo $i === 0 ? 'active' : ''; ?> <?php echo $background === '' ? 'is-fallback' : 'has-image'; ?>"
                    data-bg-image="<?php echo h($background); ?>"
                >

                    <div
                        class="witu-hero-overlay"
                        data-overlay-opacity="<?php echo h($overlay); ?>"
                    ></div>


                    <div
                        class="witu-hero-inner hero-halign-<?php echo h($halign); ?> hero-valign-<?php echo h($valign); ?>"
                    >

                        <div
                            class="witu-hero-text hero-text-<?php echo h($halign); ?>"
                        >

                            <h1>
                                <?php
                                echo h(
                                    $home['hero_headline']
                                );
                                ?>
                            </h1>


                            <p>
                                <?php
                                echo h(
                                    $home['hero_subtext']
                                );
                                ?>
                            </p>


                            <div
                                class="witu-hero-btns hero-btns-<?php echo h($halign); ?>"
                            >

                                <?php
                                if (
                                    !empty(
                                        $home['primary_cta_text']
                                    ) &&
                                    !empty(
                                        $home['primary_cta_link']
                                    )
                                ):
                                ?>

                                    <a
                                        href="<?php echo h($home['primary_cta_link']); ?>"
                                        class="witu-btn witu-btn-primary"
                                    >
                                        <?php
                                        echo h(
                                            $home['primary_cta_text']
                                        );
                                        ?>
                                    </a>

                                <?php endif; ?>


                                <?php
                                if (
                                    !empty(
                                        $home['secondary_cta_text']
                                    ) &&
                                    !empty(
                                        $home['secondary_cta_link']
                                    )
                                ):
                                ?>

                                    <a
                                        href="<?php echo h($home['secondary_cta_link']); ?>"
                                        class="witu-btn witu-btn-outline"
                                    >
                                        <?php
                                        echo h(
                                            $home['secondary_cta_text']
                                        );
                                        ?>
                                    </a>

                                <?php endif; ?>

                            </div>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>


            <?php
            if (
                count($sliders) > 1
            ):
            ?>

                <div
                    class="witu-slider-dots"
                    id="sliderDots"
                    aria-label="Slide navigation"
                >

                    <?php
                    foreach (
                        $sliders
                        as $i => $slider
                    ):
                    ?>

                        <button
                            type="button"
                            class="witu-slider-dot <?php echo $i === 0 ? 'active' : ''; ?>"
                            data-index="<?php echo (int)$i; ?>"
                            aria-label="Go to slide <?php echo (int)$i + 1; ?>"
                        ></button>

                    <?php endforeach; ?>

                </div>

                <button type="button" class="witu-hero-nav witu-hero-prev" aria-label="Previous slide">
                    <i class="fas fa-chevron-left" aria-hidden="true"></i>
                </button>

                <button type="button" class="witu-hero-nav witu-hero-next" aria-label="Next slide">
                    <i class="fas fa-chevron-right" aria-hidden="true"></i>
                </button>

                <div class="witu-hero-progress" aria-hidden="true">
                    <span class="witu-hero-progress-bar"></span>
                </div>

            <?php endif; ?>


        <?php else: ?>

            <div
                class="witu-hero-slide active is-fallback"
                data-bg-image=""
            >

                <div
                    class="witu-hero-overlay"
                    data-overlay-opacity="<?php echo h($overlay); ?>"
                ></div>


                <div
                    class="witu-hero-inner hero-halign-<?php echo h($halign); ?> hero-valign-<?php echo h($valign); ?>"
                >

                    <div
                        class="witu-hero-text hero-text-<?php echo h($halign); ?>"
                    >

                        <h1>
                            <?php
                            echo h(
                                $home['hero_headline']
                            );
                            ?>
                        </h1>


                        <p>
                            <?php
                            echo h(
                                $home['hero_subtext']
                            );
                            ?>
                        </p>


                        <div
                            class="witu-hero-btns hero-btns-<?php echo h($halign); ?>"
                        >

                            <?php
                            if (
                                !empty(
                                    $home['primary_cta_text']
                                ) &&
                                !empty(
                                    $home['primary_cta_link']
                                )
                            ):
                            ?>

                                <a
                                    href="<?php echo h($home['primary_cta_link']); ?>"
                                    class="witu-btn witu-btn-primary"
                                >
                                    <?php
                                    echo h(
                                        $home['primary_cta_text']
                                    );
                                    ?>
                                </a>

                            <?php endif; ?>


                            <?php
                            if (
                                !empty(
                                    $home['secondary_cta_text']
                                ) &&
                                !empty(
                                    $home['secondary_cta_link']
                                )
                            ):
                            ?>

                                <a
                                    href="<?php echo h($home['secondary_cta_link']); ?>"
                                    class="witu-btn witu-btn-outline"
                                >
                                    <?php
                                    echo h(
                                        $home['secondary_cta_text']
                                    );
                                    ?>
                                </a>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>

            </div>

        <?php endif; ?>

    </div>

</section>


<!-- ======================================================
     IMPACT
======================================================= -->

<section
    class="witu-section witu-impact"
>

    <div class="container">

        <div class="witu-impact-grid">

            <?php
            foreach (
                $impact_stats
                as $stat
            ):
            ?>

                <?php
                $statRaw = trim((string)($stat['stat_value'] ?? '0'));
                $statTarget = (float)preg_replace('/[^0-9.]/', '', str_replace(',', '', $statRaw));
                $statPrefix = preg_match('/^([^0-9]*)/', $statRaw, $prefixMatch)
                    ? trim((string)($prefixMatch[1] ?? ''))
                    : '';
                $statSuffix = preg_match('/([^0-9.,\\s]+)$/u', $statRaw, $suffixMatch)
                    ? trim((string)($suffixMatch[1] ?? ''))
                    : '';
                ?>

                <div class="witu-impact-card">
                    <strong
                        class="witu-stat-number"
                        data-count-target="<?php echo h((string)$statTarget); ?>"
                        data-count-prefix="<?php echo h($statPrefix); ?>"
                        data-count-suffix="<?php echo h($statSuffix); ?>"
                        aria-label="<?php echo h($statRaw); ?>"
                    >0</strong>

                    <span class="witu-impact-label">
                        <?php echo h($stat['stat_label']); ?>
                    </span>
                </div>

            <?php endforeach; ?>

        </div>

    </div>

</section>


<!-- ======================================================
     PROGRAMMES
======================================================= -->
<!-- ======================================================
     PROGRAMMES
======================================================= -->

<section
    class="witu-section witu-bg-light"
    id="programs"
>
    <div class="container">

        <div class="witu-section-title">
            <h2>Our Programs</h2>

            <p>
                WITU delivers programs that equip women and girls
                with practical skills, agency, networks and pathways
                to opportunity.
            </p>
        </div>


        <?php if (!empty($programs)): ?>

            <div class="witu-program-grid">

                <?php foreach ($programs as $program): ?>

                    <?php

                    /*
                    |--------------------------------------------------------------------------
                    | Title
                    |--------------------------------------------------------------------------
                    */

                    $programTitle = trim(
                        (string)(
                            $program['title']
                            ?? 'Programme'
                        )
                    );


                    /*
                    |--------------------------------------------------------------------------
                    | Homepage description
                    |--------------------------------------------------------------------------
                    |
                    | Use:
                    | 1. summary
                    | 2. description
                    | 3. content
                    |
                    */

                    $rawDescription = trim(
                        (string)(
                            $program['summary']
                            ?? ''
                        )
                    );


                    if ($rawDescription === '') {
                        $rawDescription = trim(
                            (string)(
                                $program['description']
                                ?? ''
                            )
                        );
                    }


                    if ($rawDescription === '') {
                        $rawDescription = trim(
                            (string)(
                                $program['content']
                                ?? ''
                            )
                        );
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Remove HTML
                    |--------------------------------------------------------------------------
                    */

                    $fullDescription = trim(
                        preg_replace(
                            '/\s+/u',
                            ' ',
                            strip_tags(
                                html_entity_decode(
                                    $rawDescription,
                                    ENT_QUOTES | ENT_HTML5,
                                    'UTF-8'
                                )
                            )
                        )
                    );


                    /*
                    |--------------------------------------------------------------------------
                    | Limit homepage card to 22 words
                    |--------------------------------------------------------------------------
                    */

                    $words = preg_split(
                        '/\s+/u',
                        $fullDescription,
                        -1,
                        PREG_SPLIT_NO_EMPTY
                    );

                    $words = $words ?: [];

                    $wordLimit = 22;


                    if (count($words) > $wordLimit) {

                        $shortDescription =
                            implode(
                                ' ',
                                array_slice(
                                    $words,
                                    0,
                                    $wordLimit
                                )
                            )
                            . '...';

                    } else {

                        $shortDescription =
                            $fullDescription;
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Featured image
                    |--------------------------------------------------------------------------
                    */

                    $featuredImage = trim(
                        (string)(
                            $program['featured_image']
                            ?? ''
                        )
                    );


                    if ($featuredImage !== '') {

                        /*
                         * External URL
                         */
                        if (
                            preg_match(
                                '#^(https?:)?//#i',
                                $featuredImage
                            )
                        ) {

                            $programImageUrl =
                                $featuredImage;


                        /*
                         * Already absolute path
                         */
                        } elseif (
                            str_starts_with(
                                $featuredImage,
                                '/'
                            )
                        ) {

                            $programImageUrl =
                                $featuredImage;


                        /*
                         * Already contains uploads/assets path
                         */
                        } elseif (
                            str_starts_with(
                                $featuredImage,
                                'uploads/'
                            ) ||
                            str_starts_with(
                                $featuredImage,
                                'assets/'
                            )
                        ) {

                            $programImageUrl =
                                $featuredImage;


                        /*
                         * Database only contains filename
                         */
                        } else {

                            $programImageUrl =
                                'uploads/programmes/'
                                . ltrim(
                                    $featuredImage,
                                    '/'
                                );
                        }

                    } else {

                        $programImageUrl =
                            'assets/img/program-placeholder.jpg';
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Programme slug / URL
                    |--------------------------------------------------------------------------
                    */

                    $programSlug = trim(
                        (string)(
                            $program['slug']
                            ?? ''
                        )
                    );


                    if ($programSlug !== '') {

                        $programUrl =
                            'programme.php?slug='
                            . rawurlencode(
                                $programSlug
                            );

                    } else {

                        $programUrl =
                            'programme.php?id='
                            . (int)(
                                $program['id']
                                ?? 0
                            );
                    }

                    ?>


                    <article class="witu-program-card">


                        <!-- Image -->

                        <a
                            href="<?php echo h($programUrl); ?>"
                            class="witu-program-image-link"
                            aria-label="Learn more about <?php echo h($programTitle); ?>"
                        >

                            <img
                                class="witu-program-img"
                                src="<?php echo h($programImageUrl); ?>"
                                alt="<?php echo h($programTitle); ?>"
                                loading="lazy"
                            >

                        </a>


                        <!-- Content -->

                        <div class="witu-program-body">

                            <h3>
                                <?php echo h($programTitle); ?>
                            </h3>


                            <?php if ($shortDescription !== ''): ?>

                                <p class="witu-program-description">
                                    <?php
                                    echo h(
                                        $shortDescription
                                    );
                                    ?>
                                </p>

                            <?php endif; ?>


                            <a
                                href="<?php echo h($programUrl); ?>"
                                class="
                                    witu-btn
                                    witu-btn-primary
                                    witu-program-more
                                "
                                aria-label="Learn more about <?php echo h($programTitle); ?>"
                            >
                                Learn More

                                <i
                                    class="fas fa-arrow-right"
                                    aria-hidden="true"
                                ></i>
                            </a>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>


        <?php else: ?>

            <div class="witu-empty-state">
                <p>
                    No programmes are currently available.
                </p>
            </div>

        <?php endif; ?>

    </div>
</section>
<!-- ======================================================
     SKILLS, AGENCY & OPPORTUNITY
======================================================= -->

<section
    class="witu-section witu-bg-cream"
    id="agency"
>

    <div class="container">

        <div class="witu-section-title">

            <h2>
                Skills, Agency &amp; Opportunity Pathways
            </h2>

            <p>
                WITU combines technical training with agency building,
                personalized support, and direct connections to employers,
                entrepreneurship ecosystems, and income-generating opportunities.
            </p>

        </div>


        <div class="witu-agency-grid">

            <?php
            foreach (
                $agency_cards
                as $agency
            ):
            ?>

                <div class="witu-agency-card">

                    <?php
                    if (
                        !empty(
                            $agency['icon']
                        )
                    ):
                    ?>

                        <div class="witu-agency-icon">

                            <i
                                class="<?php echo h($agency['icon']); ?>"
                                aria-hidden="true"
                            ></i>

                        </div>

                    <?php endif; ?>


                    <h3>
                        <?php
                        echo h(
                            $agency['title']
                        );
                        ?>
                    </h3>


                    <p>
                        <?php
                        echo h(
                            $agency['description']
                        );
                        ?>
                    </p>

                </div>

            <?php endforeach; ?>

        </div>

    </div>

</section>


<?php

$journeyVideo =
    sectionVideo(
        $journeySectionSettings
    );


$journeyHasVisual =
    hasVisualBg(
        $journeySectionSettings
    );

?>


<!-- ======================================================
     PARTICIPANT JOURNEY
======================================================= -->

<section
    class="
        witu-section
        witu-journey
        section-bg-dynamic
        <?php echo $journeyHasVisual ? 'has-visual-bg' : ''; ?>
    "
    id="participant-journey"
    data-bg-type="<?php echo h($journeySectionSettings['bg_type'] ?? 'color'); ?>"
    data-bg-color="<?php echo h($journeySectionSettings['bg_color'] ?? '#ffffff'); ?>"
    data-bg-image="<?php echo h(assetUrl($journeySectionSettings['bg_image'] ?? '')); ?>"
    data-overlay-color="<?php echo h($journeySectionSettings['overlay_color'] ?? 'rgba(11,44,58,.55)'); ?>"
>

    <?php
    if (
        $journeyVideo !== ''
    ):
    ?>

        <video
            class="section-bg-video"
            autoplay
            muted
            loop
            playsinline
        >

            <source
                src="<?php echo h($journeyVideo); ?>"
            >

        </video>

    <?php endif; ?>


    <div class="container">

        <div class="witu-section-title">

            <h2>
                <?php
                echo h(
                    $journeySectionSettings['section_title']
                    ?: 'The Participant Journey'
                );
                ?>
            </h2>


            <p>
                <?php
                echo h(
                    $journeySectionSettings['section_subtitle']
                    ?: $journeyDefaults[1]['description']
                );
                ?>
            </p>

        </div>


        <div class="witu-journey-grid">

            <?php
            foreach (
                [1, 2, 3]
                as $step
            ):
            ?>

                <?php

                $journeyItem =
                    $journeySteps[$step] ??
                    $journeyDefaults[$step];


                $title =
                    h(
                        html_entity_decode(
                            (string)(
                                $journeyItem['title'] ??
                                $journeyDefaults[$step]['title']
                            ),
                            ENT_QUOTES | ENT_HTML5,
                            'UTF-8'
                        )
                    );


                $description =
                    nl2br(
                        h(
                            html_entity_decode(
                                trim(
                                    (string)(
                                        $journeyItem['description'] ??
                                        $journeyDefaults[$step]['description']
                                    )
                                ),
                                ENT_QUOTES | ENT_HTML5,
                                'UTF-8'
                            )
                        )
                    );


                $stat =
                    h(
                        html_entity_decode(
                            trim(
                                (string)(
                                    $journeyItem['stat_text'] ??
                                    ''
                                )
                            ),
                            ENT_QUOTES | ENT_HTML5,
                            'UTF-8'
                        )
                    );

                ?>

                <div class="witu-journey-card">

                    <div class="witu-step">
                        <?php echo (int)$step; ?>
                    </div>


                    <h3>
                        <?php echo $title; ?>
                    </h3>


                    <p>

                        <?php
                        echo $description;
                        ?>


                        <?php
                        if (
                            $stat !== ''
                        ):
                        ?>

                            <span class="witu-journey-stat">
                                <?php echo $stat; ?>
                            </span>

                        <?php endif; ?>

                    </p>

                </div>

            <?php endforeach; ?>

        </div>


        <?php

        $continuous =
            $journeySteps['continuous'] ??
            $journeyDefaults['continuous'];


        $continuousTitle =
            h(
                html_entity_decode(
                    (string)(
                        $continuous['title'] ??
                        $journeyDefaults['continuous']['title']
                    ),
                    ENT_QUOTES | ENT_HTML5,
                    'UTF-8'
                )
            );


        $continuousDescription =
            nl2br(
                h(
                    html_entity_decode(
                        trim(
                            (string)(
                                $continuous['description'] ??
                                $journeyDefaults['continuous']['description']
                            )
                        ),
                        ENT_QUOTES | ENT_HTML5,
                        'UTF-8'
                    )
                )
            );

        ?>


        <div class="witu-support-box">

            <h3>
                <?php
                echo $continuousTitle;
                ?>
            </h3>


            <p>
                <?php
                echo $continuousDescription;
                ?>
            </p>

        </div>

    </div>

</section>


<!-- ======================================================
     TESTIMONIALS
======================================================= -->

<section
    class="witu-section witu-bg-light"
    id="stories"
>

    <div class="container">

        <div class="witu-section-title">

            <h2>
                In Their Own Words
            </h2>

            <p>
                Real stories of transformation from WITU participants
                and graduates.
            </p>

        </div>


        <?php
        if (
            !empty(
                $testimonials
            )
        ):
        ?>

            <div
                class="witu-t-wrap"
                id="testimonialsCarousel"
            >

                <?php
                if (
                    count($testimonials) > 1
                ):
                ?>

                    <button
                        type="button"
                        class="witu-t-nav witu-t-prev"
                        aria-label="Previous testimonial"
                    >
                        <i
                            class="fas fa-chevron-left"
                            aria-hidden="true"
                        ></i>
                    </button>

                <?php endif; ?>


                <div class="witu-t-carousel">

                    <div
                        class="witu-t-track"
                        id="tTrack"
                    >

                        <?php
                        foreach (
                            $testimonials
                            as $testimonial
                        ):
                        ?>

                            <?php

                            $fullQuote =
                                trim(
                                    (string)(
                                        $testimonial['quote'] ??
                                        ''
                                    )
                                );


                            $quoteWords =
                                preg_split(
                                    '/\s+/u',
                                    $fullQuote,
                                    -1,
                                    PREG_SPLIT_NO_EMPTY
                                );


                            $quoteWords =
                                $quoteWords ?: [];


                            $shortQuote =
                                implode(
                                    ' ',
                                    array_slice(
                                        $quoteWords,
                                        0,
                                        20
                                    )
                                );


                            $needsMore =
                                count(
                                    $quoteWords
                                ) > 20;


                            $metaParts =
                                array_filter(
                                    [
                                        trim(
                                            (string)(
                                                $testimonial['program'] ??
                                                ''
                                            )
                                        ),

                                        trim(
                                            (string)(
                                                $testimonial['location'] ??
                                                ''
                                            )
                                        ),
                                    ],
                                    static fn($value) =>
                                        $value !== ''
                                );


                            $meta =
                                implode(
                                    ' - ',
                                    $metaParts
                                );


                            $payload = [

                                'id' =>
                                    (int)(
                                        $testimonial['id'] ??
                                        0
                                    ),

                                'full_name' =>
                                    $testimonial['full_name'] ??
                                    '',

                                'program' =>
                                    $testimonial['program'] ??
                                    '',

                                'location' =>
                                    $testimonial['location'] ??
                                    '',

                                'photo' =>
                                    $testimonial['photo'] ??
                                    '',

                                'quote' =>
                                    $fullQuote,

                            ];

                            ?>

                            <div class="witu-t-slide">

                                <div class="witu-t-card">

                                    <div class="witu-t-top">

                                        <?php
                                        if (
                                            !empty(
                                                $testimonial['photo']
                                            )
                                        ):
                                        ?>

                                            <img
                                                class="witu-t-photo"
                                                src="uploads/testimonials/<?php echo h($testimonial['photo']); ?>"
                                                alt="<?php echo h($testimonial['full_name']); ?>"
                                                loading="lazy"
                                            >

                                        <?php else: ?>

                                            <div
                                                class="witu-t-photo witu-t-photo-placeholder"
                                                aria-hidden="true"
                                            ></div>

                                        <?php endif; ?>


                                        <div class="witu-t-meta">

                                            <strong>
                                                <?php
                                                echo h(
                                                    $testimonial['full_name']
                                                );
                                                ?>
                                            </strong>


                                            <?php
                                            if (
                                                $meta !== ''
                                            ):
                                            ?>

                                                <small>
                                                    <?php
                                                    echo h(
                                                        $meta
                                                    );
                                                    ?>
                                                </small>

                                            <?php endif; ?>

                                        </div>

                                    </div>


                                    <p class="witu-t-quote">

                                        &ldquo;<?php
                                        echo h(
                                            $needsMore
                                            ? $shortQuote . '...'
                                            : $shortQuote
                                        );
                                        ?>&rdquo;

                                    </p>


                                    <?php
                                    if (
                                        $needsMore
                                    ):
                                    ?>

                                        <a
                                            href="#"
                                            class="witu-t-more"
                                            onclick='
                                                event.preventDefault();
                                                openTestimonialModal(
                                                    <?php
                                                    echo json_encode(
                                                        $payload,
                                                        JSON_UNESCAPED_SLASHES |
                                                        JSON_UNESCAPED_UNICODE |
                                                        JSON_HEX_APOS |
                                                        JSON_HEX_QUOT |
                                                        JSON_HEX_TAG |
                                                        JSON_HEX_AMP
                                                    );
                                                    ?>
                                                );
                                            '
                                        >
                                            Read more
                                        </a>

                                    <?php endif; ?>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </div>


                <?php
                if (
                    count($testimonials) > 1
                ):
                ?>

                    <button
                        type="button"
                        class="witu-t-nav witu-t-next"
                        aria-label="Next testimonial"
                    >
                        <i
                            class="fas fa-chevron-right"
                            aria-hidden="true"
                        ></i>
                    </button>


                    <div
                        class="witu-t-dots"
                        id="tDots"
                    ></div>

                <?php endif; ?>

            </div>

        <?php endif; ?>

    </div>

</section>


<?php

$partnerVideo =
    sectionVideo(
        $partnerSectionSettings
    );


$partnerHasVisual =
    hasVisualBg(
        $partnerSectionSettings
    );

?>


<!-- ======================================================
     PARTNERS
======================================================= -->

<section
    class="
        witu-section
        section-bg-dynamic
        <?php echo $partnerHasVisual ? 'has-visual-bg' : ''; ?>
    "
    id="partners"
    data-bg-type="<?php echo h($partnerSectionSettings['bg_type'] ?? 'color'); ?>"
    data-bg-color="<?php echo h($partnerSectionSettings['bg_color'] ?? '#ffffff'); ?>"
    data-bg-image="<?php echo h(assetUrl($partnerSectionSettings['bg_image'] ?? '')); ?>"
    data-overlay-color="<?php echo h($partnerSectionSettings['overlay_color'] ?? 'rgba(11,44,58,.55)'); ?>"
>

    <?php
    if (
        $partnerVideo !== ''
    ):
    ?>

        <video
            class="section-bg-video"
            autoplay
            muted
            loop
            playsinline
        >

            <source
                src="<?php echo h($partnerVideo); ?>"
            >

        </video>

    <?php endif; ?>


    <div class="container">

        <div class="witu-section-title">

            <h2>
                <?php
                echo h(
                    $partnerSectionSettings['page_title']
                    ?: 'Our Partners'
                );
                ?>
            </h2>


            <p>
                <?php
                echo h(
                    $partnerSectionSettings['page_subtitle']
                    ?: "Organizations supporting WITU's work and ecosystem"
                );
                ?>
            </p>

        </div>


        <?php
        if (
            !empty(
                $partners
            )
        ):
        ?>

            <div class="witu-partner-grid">

                <?php
                foreach (
                    $partners
                    as $partner
                ):
                ?>

                    <?php

                    $partnerWebsite =
                        trim(
                            (string)(
                                $partner['website'] ??
                                ''
                            )
                        );


                    if (
                        $partnerWebsite === ''
                    ) {
                        $partnerWebsite = '#';
                    }


                    $partnerLogo =
                        assetUrl(
                            $partner['logo'] ?? '',
                            'uploads/partners'
                        );

                    ?>

                    <a
                        class="witu-partner"
                        href="<?php echo h($partnerWebsite); ?>"
                        target="<?php echo $partnerWebsite !== '#' ? '_blank' : '_self'; ?>"
                        rel="<?php echo $partnerWebsite !== '#' ? 'noopener noreferrer' : ''; ?>"
                        title="<?php echo h($partner['name']); ?>"
                    >

                        <?php
                        if (
                            $partnerLogo !== ''
                        ):
                        ?>

                            <img
                                src="<?php echo h($partnerLogo); ?>"
                                alt="<?php echo h($partner['name']); ?>"
                                loading="lazy"
                            >

                        <?php else: ?>

                            <span>
                                <?php
                                echo h(
                                    $partner['name']
                                );
                                ?>
                            </span>

                        <?php endif; ?>

                    </a>

                <?php endforeach; ?>

            </div>


        <?php else: ?>

            <div class="witu-partner-grid">

                <?php

                $defaultPartners = [
                    'Google',
                    'Adobe',
                    'Cadence',
                    'NITA-U',
                    'UCC',
                    'UNICEF',
                    'Mastercard Foundation',
                    'NSSF',
                    'Zendesk',
                    'Stanbic Bank',
                    'Team4Tech',
                    'Forward',
                ];

                ?>


                <?php
                foreach (
                    $defaultPartners
                    as $partnerName
                ):
                ?>

                    <div
                        class="witu-partner witu-partner-text"
                    >
                        <?php
                        echo h(
                            $partnerName
                        );
                        ?>
                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </div>

</section>


<!-- ======================================================
     GET INVOLVED
======================================================= -->

<section
    class="witu-cta"
    id="get-involved"
>

    <div class="container">

        <h2>
            Get Involved
        </h2>


        <p>
            Join us in creating pathways to technology, agency,
            dignified work, entrepreneurship, and economic opportunity
            for women and girls.
        </p>


        <div class="witu-cta-btns">

           <!-- <?php
            if (
                !empty(
                    $home['get_involved_link']
                )
            ):
            ?>

                <a
                    href="<?php echo h($home['get_involved_link']); ?>"
                    class="witu-btn witu-btn-white"
                >
                    <?php
                    echo h(
                        $home['get_involved_text'] ??
                        'Get Involved'
                    );
                    ?>
                </a>

            <?php endif; ?> -->


            <a
                href="donate.php"
                class="witu-btn witu-btn-white"
            >
                Donate
            </a>


            <a
                href="partner.php"
                class="witu-btn witu-btn-outline-white"
            >
                Partner With Us
            </a>


            <a
                href="hire-graduate.php"
                class="witu-btn witu-btn-outline-white"
            >
                Hire Our Graduates
            </a>


            <a
                href="volunteer.php"
                class="witu-btn witu-btn-outline-white"
            >
                Volunteer
            </a>

        </div>

    </div>

</section>


<!-- ======================================================
     HERO SLIDER JAVASCRIPT
======================================================= -->

<script>
(function () {
    'use strict';

    const slider = document.getElementById('heroSlider');
    if (!slider) return;

    const slides = Array.from(slider.querySelectorAll('.witu-hero-slide'));
    const dots = Array.from(slider.querySelectorAll('.witu-slider-dot'));
    const previousButton = slider.querySelector('.witu-hero-prev');
    const nextButton = slider.querySelector('.witu-hero-next');
    const progressBar = slider.querySelector('.witu-hero-progress-bar');

    if (!slides.length) return;

    const delay = 6500;
    let index = 0;
    let timer = null;
    let pointerStartX = null;

    function applyBackgrounds() {
        slides.forEach(function (slide) {
            const image = (slide.dataset.bgImage || '').trim();
            const overlay = slide.querySelector('.witu-hero-overlay');
            const opacity = overlay
                ? Math.max(0, Math.min(.9, Number.parseFloat(overlay.dataset.overlayOpacity || '.48')))
                : .48;

            slide.style.backgroundImage = image
                ? 'url("' + image.replace(/"/g, '\\"') + '")'
                : 'linear-gradient(135deg,#0b1b3a 0%,#1a3a6e 52%,#6f3f74 100%)';

            if (overlay) {
                overlay.style.background =
                    'linear-gradient(90deg,rgba(0,0,0,' +
                    Math.min(.84, opacity + .18) +
                    '),rgba(45,14,48,' +
                    Math.max(.18, opacity - .08) +
                    '))';
            }
        });
    }

    function restartProgress() {
        if (!progressBar) return;
        progressBar.classList.remove('is-running');
        void progressBar.offsetWidth;
        progressBar.classList.add('is-running');
    }

    function showSlide(nextIndex, direction) {
        const previousIndex = index;
        index = (nextIndex + slides.length) % slides.length;

        slides.forEach(function (slide, slideIndex) {
            slide.setAttribute('aria-hidden', slideIndex === index ? 'false' : 'true');
            slide.inert = slideIndex !== index;
            slide.classList.remove('active', 'is-leaving', 'from-left', 'from-right');

            if (slideIndex === previousIndex && previousIndex !== index) {
                slide.classList.add('is-leaving');
            }

            if (slideIndex === index) {
                slide.classList.add('active');
                if (direction === 'previous') slide.classList.add('from-left');
                if (direction === 'next') slide.classList.add('from-right');
            }
        });

        dots.forEach(function (dot, dotIndex) {
            const active = dotIndex === index;
            dot.classList.toggle('active', active);
            dot.setAttribute('aria-current', active ? 'true' : 'false');
        });

        if (timer !== null) restartProgress();
    }

    function nextSlide() { showSlide(index + 1, 'next'); }
    function previousSlide() { showSlide(index - 1, 'previous'); }

    function stopSlider() {
        if (timer !== null) {
            window.clearInterval(timer);
            timer = null;
        }
        if (progressBar) progressBar.classList.remove('is-running');
    }

    function startSlider() {
        stopSlider();
        // Manual navigation lets visitors control the reading pace.
    }

    dots.forEach(function (dot) {
        dot.addEventListener('click', function () {
            const selected = Number.parseInt(dot.dataset.index || '0', 10);
            const safeIndex = Number.isNaN(selected) ? 0 : selected;
            showSlide(safeIndex, safeIndex < index ? 'previous' : 'next');
            startSlider();
        });
    });

    if (previousButton) {
        previousButton.addEventListener('click', function () {
            previousSlide();
            startSlider();
        });
    }

    if (nextButton) {
        nextButton.addEventListener('click', function () {
            nextSlide();
            startSlider();
        });
    }

    slider.addEventListener('mouseenter', stopSlider);
    slider.addEventListener('mouseleave', startSlider);
    slider.addEventListener('focusin', stopSlider);
    slider.addEventListener('focusout', startSlider);

    slider.addEventListener('pointerdown', function (event) {
        if (event.pointerType === 'mouse' && event.button !== 0) return;
        pointerStartX = event.clientX;
    });

    slider.addEventListener('pointerup', function (event) {
        if (pointerStartX === null) return;
        const distance = event.clientX - pointerStartX;
        pointerStartX = null;
        if (Math.abs(distance) < 45) return;
        distance < 0 ? nextSlide() : previousSlide();
        startSlider();
    });

    document.addEventListener('visibilitychange', function () {
        document.hidden ? stopSlider() : startSlider();
    });

    applyBackgrounds();
    showSlide(0);
    startSlider();
})();
</script>


<!-- ======================================================
     TESTIMONIAL CAROUSEL
======================================================= -->

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        'use strict';


        const carousel =
            document.getElementById(
                'testimonialsCarousel'
            );


        const track =
            document.getElementById(
                'tTrack'
            );


        const dotsWrap =
            document.getElementById(
                'tDots'
            );


        if (
            !carousel ||
            !track
        ) {
            return;
        }


        const slides =
            Array.from(
                track.querySelectorAll(
                    '.witu-t-slide'
                )
            );


        const previousButton =
            carousel.querySelector(
                '.witu-t-prev'
            );


        const nextButton =
            carousel.querySelector(
                '.witu-t-next'
            );


        if (
            slides.length <= 1
        ) {
            return;
        }


        let index = 0;

        let timer = null;

        let perView =
            getPerView();

        let maxIndex =
            Math.max(
                0,
                slides.length -
                perView
            );


        function getPerView() {

            if (
                window.innerWidth <=
                576
            ) {
                return 1;
            }


            if (
                window.innerWidth <=
                992
            ) {
                return 2;
            }


            return 3;
        }


        function refreshLimits() {

            perView =
                getPerView();


            maxIndex =
                Math.max(
                    0,
                    slides.length -
                    perView
                );


            if (
                index >
                maxIndex
            ) {
                index = 0;
            }

        }


        function updateCarousel() {

            refreshLimits();


            if (
                slides.length === 0
            ) {
                return;
            }


            const slideWidth =
                slides[0]
                    .getBoundingClientRect()
                    .width;


            track.style.transform =
                'translateX(-' +
                (
                    index *
                    slideWidth
                ) +
                'px)';


            if (dotsWrap) {

                const dots =
                    dotsWrap.querySelectorAll(
                        '.witu-t-dot'
                    );


                dots.forEach(
                    function (
                        dot,
                        dotIndex
                    ) {

                        dot.classList.toggle(
                            'active',
                            dotIndex === index
                        );

                    }
                );

            }

        }


        function buildDots() {

            if (
                !dotsWrap
            ) {
                return;
            }


            refreshLimits();


            dotsWrap.innerHTML =
                '';


            if (
                maxIndex <= 0
            ) {
                return;
            }


            for (
                let dotIndex = 0;
                dotIndex <= maxIndex;
                dotIndex++
            ) {

                const dot =
                    document.createElement(
                        'button'
                    );


                dot.type =
                    'button';


                dot.className =
                    'witu-t-dot';


                dot.setAttribute(
                    'aria-label',
                    'Go to testimonial slide ' +
                    (
                        dotIndex + 1
                    )
                );


                dot.addEventListener(
                    'click',
                    function () {

                        index =
                            dotIndex;


                        updateCarousel();

                        restartAuto();

                    }
                );


                dotsWrap.appendChild(
                    dot
                );

            }

        }


        function nextSlide() {

            refreshLimits();


            index =
                index >= maxIndex
                ? 0
                : index + 1;


            updateCarousel();

        }


        function previousSlide() {

            refreshLimits();


            index =
                index <= 0
                ? maxIndex
                : index - 1;


            updateCarousel();

        }


        function stopAuto() {

            if (timer) {

                clearInterval(
                    timer
                );

                timer = null;
            }

        }


        function startAuto() {

            stopAuto();

            refreshLimits();


            if (
                maxIndex <= 0
            ) {
                return;
            }


            // Testimonials advance only when the visitor chooses.

        }


        function restartAuto() {

            stopAuto();

            startAuto();

        }


        if (nextButton) {

            nextButton.addEventListener(
                'click',
                function () {

                    nextSlide();

                    restartAuto();

                }
            );

        }


        if (previousButton) {

            previousButton.addEventListener(
                'click',
                function () {

                    previousSlide();

                    restartAuto();

                }
            );

        }


        carousel.addEventListener(
            'mouseenter',
            stopAuto
        );


        carousel.addEventListener(
            'mouseleave',
            startAuto
        );


        window.addEventListener(
            'resize',
            function () {

                buildDots();

                updateCarousel();

                restartAuto();

            }
        );


        buildDots();

        updateCarousel();

        startAuto();

    }
);

</script>



<!-- ======================================================
     DYNAMIC SECTION BACKGROUNDS & STATISTIC COUNTERS
======================================================= -->

<script>
(function () {
    'use strict';

    document.querySelectorAll('.section-bg-dynamic').forEach(function (section) {
        const type = (section.dataset.bgType || 'color').toLowerCase();
        const colour = (section.dataset.bgColor || '#ffffff').trim();
        const image = (section.dataset.bgImage || '').trim();
        const overlay = (section.dataset.overlayColor || 'rgba(11,44,58,.55)').trim();

        section.style.backgroundColor = colour || '#ffffff';
        section.style.setProperty('--section-overlay', overlay);

        if (type === 'image' && image !== '') {
            section.style.backgroundImage = 'url("' + image.replace(/"/g, '\\"') + '")';
        } else {
            section.style.backgroundImage = 'none';
        }
    });

    const counters = Array.from(document.querySelectorAll('.witu-stat-number[data-count-target]'));
    if (!counters.length) return;

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function formatNumber(value, target) {
        return new Intl.NumberFormat('en-UG', {
            maximumFractionDigits: Number.isInteger(target) ? 0 : 1
        }).format(value);
    }

    function finish(counter) {
        const target = Number.parseFloat(counter.dataset.countTarget || '0');
        counter.textContent =
            (counter.dataset.countPrefix || '') +
            formatNumber(target, target) +
            (counter.dataset.countSuffix || '');
    }

    function animate(counter) {
        if (counter.dataset.animated === '1') return;
        counter.dataset.animated = '1';

        const target = Number.parseFloat(counter.dataset.countTarget || '0');
        if (!Number.isFinite(target) || target <= 0 || reduceMotion) {
            finish(counter);
            return;
        }

        const prefix = counter.dataset.countPrefix || '';
        const suffix = counter.dataset.countSuffix || '';
        const duration = 1800;
        const start = performance.now();

        function tick(now) {
            const progress = Math.min(1, (now - start) / duration);
            const eased = 1 - Math.pow(1 - progress, 3);
            const current = target * eased;
            const shown = Number.isInteger(target) ? Math.round(current) : current;

            counter.textContent = prefix + formatNumber(shown, target) + suffix;

            if (progress < 1) {
                requestAnimationFrame(tick);
            } else {
                finish(counter);
            }
        }

        requestAnimationFrame(tick);
    }

    if ('IntersectionObserver' in window && !reduceMotion) {
        const observer = new IntersectionObserver(function (entries, instance) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                animate(entry.target);
                instance.unobserve(entry.target);
            });
        }, {threshold: .4});

        counters.forEach(function (counter) {
            observer.observe(counter);
        });
    } else {
        counters.forEach(finish);
    }
})();
</script>


<?php
include 'footer.php';
?>