</main>
<!-- Footer -->
<footer class="site-footer">
    <style>

    </style>

    <div class="container">
        <div class="footer-grid">

            <!-- About Section -->
            <div class="footer-col">
                <h4><?php echo htmlspecialchars($site_name); ?></h4>

                <p>
                    <?php echo htmlspecialchars(
                        getSetting(
                            $conn,
                            'footer_about',
                            'Advancing opportunity for women & girls through technology, entrepreneurship, and digital inclusion.'
                        )
                    ); ?>
                </p>

                <?php
                $social = [
                    'facebook'  => getSetting($conn, 'social_facebook'),
                    'twitter'   => getSetting($conn, 'social_twitter'),
                    'linkedin'  => getSetting($conn, 'social_linkedin'),
                    'instagram' => getSetting($conn, 'social_instagram'),
                ];

                $social = array_filter($social);

                $iconMap = [
                    'facebook'  => 'facebook-f',
                    'twitter'   => 'twitter',
                    'linkedin'  => 'linkedin-in',
                    'instagram' => 'instagram',
                ];

                if (!empty($social)):
                ?>
                    <div class="footer-social">
                        <h4>Follow Us</h4>

                        <div class="footer-social-icons">
                            <?php foreach ($social as $network => $url): ?>
                                <a class="footer-social-icon"
                                   href="<?php echo htmlspecialchars($url, ENT_QUOTES); ?>"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   aria-label="<?php echo htmlspecialchars(ucfirst($network)); ?>">
                                    <i class="fa-brands fa-<?php echo htmlspecialchars($iconMap[$network] ?? $network); ?>"></i>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Quick Links -->
            <div class="footer-col">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="about-us.php">About</a></li>
                    <li><a href="programmes.php">Programs</a></li>
                    <li><a href="our-impact.php">Impact</a></li>
                </ul>
            </div>

            <!-- Get Involved -->
            <div class="footer-col">
                <h4>Get Involved</h4>
                <ul>
                    <?php if (isFeatureEnabled($conn, 'donations')): ?>
                        <li>
                            <a href="donate.php" class="footer-donate-btn">
                              
                                Donate
                            </a>
                        </li>
                    <?php endif; ?>

                    <li><a href="volunteer.php">Volunteer</a></li>
                    <li><a href="partner.php">Partner With Us</a></li>
                    <li><a href="hire-graduate.php">Hire Graduate</a></li>
                </ul>
            </div>

            <!-- Contact Info -->
            <div class="footer-col">
                <h4>Contact</h4>
                <ul class="contact-info">
                    <?php if ($email = getSetting($conn, 'site_email')): ?>
                        <li>
                            <i class="fas fa-envelope"></i>
                            <a href="mailto:<?php echo htmlspecialchars($email, ENT_QUOTES); ?>">
                                <?php echo htmlspecialchars($email); ?>
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if ($phone = getSetting($conn, 'site_phone')): ?>
                        <li>
                            <i class="fas fa-phone"></i>
                            <a href="tel:<?php echo preg_replace('/[^0-9+]/', '', $phone); ?>">
                                <?php echo htmlspecialchars($phone); ?>
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if ($address = getSetting($conn, 'site_address')): ?>
                        <li>
                            <i class="fas fa-map-marker-alt"></i>
                            <span><?php echo nl2br(htmlspecialchars($address)); ?></span>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- Footer Bottom -->
        <div class="footer-bottom">
            <p>
                <?php echo getSetting($conn, 'footer_text', '&copy; ' . date('Y') . ' WITU IN TECHNOLOGY UGANDA. All rights reserved.'); ?>
            </p>

            <div class="footer-links">
                <?php
                $footer_pages = [];
                $result = $conn->query("
                    SELECT *
                    FROM pages
                    WHERE slug IN ('privacy-policy', 'terms-of-service')
                      AND status = 'published'
                ");

                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $footer_pages[] = $row;
                    }
                }

                foreach ($footer_pages as $page):
                ?>
                    <a href="page.php?slug=<?php echo htmlspecialchars($page['slug']); ?>">
                        <?php echo htmlspecialchars($page['title']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</footer>

<script src="assets/js/site.js"></script>

<?php if (isset($additional_scripts)): ?>
    <?php echo $additional_scripts; ?>
<?php endif; ?>

<?php if (isFeatureEnabled($conn, 'tracking')): ?>
    <?php if ($ga_id = getSetting($conn, 'google_analytics_id')): ?>
        <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo htmlspecialchars($ga_id, ENT_QUOTES); ?>"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', '<?php echo htmlspecialchars($ga_id, ENT_QUOTES); ?>');
        </script>
    <?php endif; ?>

    <?php if ($fb_pixel = getSetting($conn, 'facebook_pixel_id')): ?>
        <script>
            !function(f,b,e,v,n,t,s)
            {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
            n.callMethod.apply(n,arguments):n.queue.push(arguments)};
            if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
            n.queue=[];t=b.createElement(e);t.async=!0;
            t.src=v;s=b.getElementsByTagName(e)[0];
            s.parentNode.insertBefore(t,s)}(window, document,'script',
            'https://connect.facebook.net/en_US/fbevents.js');
            fbq('init', '<?php echo htmlspecialchars($fb_pixel, ENT_QUOTES); ?>');
            fbq('track', 'PageView');
        </script>

        <noscript>
            <img height="1"
                 width="1"
                 style="display:none"
                 src="https://www.facebook.com/tr?id=<?php echo htmlspecialchars($fb_pixel, ENT_QUOTES); ?>&ev=PageView&noscript=1"/>
        </noscript>
    <?php endif; ?>
<?php endif; ?>

</body>
</html>
