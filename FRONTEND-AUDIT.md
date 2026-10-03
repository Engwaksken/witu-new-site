# WITU frontend audit and focused restructure

Date: 3 October 2026. Scope: public shared layout and homepage interaction, reviewed from source. Audience: women and girls exploring technology opportunities, plus volunteers, employers and partners. No demographic-specific visual stereotype is required: clarity, dignity, accessibility and useful pathways guide this work.

## Findings and implemented improvements

| Priority | Finding | Change |
| --- | --- | --- |
| High | `site.js` redefined the header's mobile-menu function using a conflicting CSS class. | Removed duplicate navigation implementation; header controls the drawer consistently. |
| High | Dropdown state was not communicated to assistive technology; mobile close lacked focus restoration and Escape handling. | Added controlled dropdown IDs and expanded state, Escape handling, initial drawer focus, focus wrap and explicit close focus restoration. Closed mobile dropdowns and the drawer are hidden from keyboard navigation. |
| High | The main landmark was not closed before the footer, and keyboard users lacked a content shortcut. | Added a focusable content target, skip link and main closing tag before footer. |
| High | Bright gradients behind white button labels reduced readability. | Added a shared dark burnt-orange action palette with 6.39:1 white-label contrast, distinct hover states and conspicuous keyboard focus. Decorative brand colours remain in existing content. |
| High | Hero and testimonial content advanced automatically, disrupting reading pace. | Made both carousels visitor controlled. Existing arrows, dots and hero swipe remain; inactive hero slides become inert and hidden from assistive technology. |
| Medium | Key opportunities were less discoverable, and footer Volunteer led to Contact. | Added direct Programmes navigation and corrected Volunteer to its existing page. |
| Medium | Small carousel targets and mobile statistic labels hindered use. | Enlarged carousel controls to 44px targets and statistic labels to 14px; improved narrow-screen button and footer layout. |
| Medium | Dense decoration competed with content hierarchy. | Reduced button sheen, refined section spacing and headings, constrained reading widths, added subtle card boundaries and a neutral page canvas. |
| Medium | Hash-link code attempted invalid selectors and ignored motion preference; back-to-top lacked a name. | Resolve fragment IDs safely, focus the skip-link target, respect reduced motion and label back-to-top. |
| High | Contact, volunteering, partnership and employer labels were visually present but not associated with their fields. | Added explicit label associations, appropriate autocomplete and telephone input types without changing backend field names. |
| Medium | Course search/category controls lacked accessible names and submitted unexpectedly on change. | Named controls and added an explicit Apply filters action. |
| Medium | Blog articles nested a second main landmark. | Replaced the nested landmark with a content column and protected embedded media/code from narrow-screen overflow. |
| High | Shared helpers relied on an implicit event, showed inaccessible validation feedback and did not handle clipboard failures. | Root agent fixed amount selection, field-error semantics and first-invalid-field focus, accessible clipboard status/manual fallback, image-loading fallback and reduced-motion helpers. The unused newsletter helper now requires a real backend action rather than reporting a fabricated subscription. |

## Validation performed

- PHP syntax checks passed for all 31 top-level PHP files.
- `node --check assets/js/site.js` passed; header and homepage inline scripts parse successfully. Node VM behavior checks passed for amount selection and its pressed state, clipboard fallback, and newsletter backend submission.
- All 12 unique literal local PHP destinations in shared header/footer exist in the repository.
- New stylesheet braces balance; action contrast was calculated from sRGB relative luminance.
- No AGENTS.md instructions were present in this project. Existing legacy encoding of `index.php` and `site.css` was preserved; refinements are isolated in `assets/css/frontend-refinements.css`.

## Remaining verification and recommendations

The configured local MySQL service refused connection. The data-dependent site could not be rendered end to end, so viewport screenshots, real keyboard interaction, assistive-technology behavior, CMS menu size, image quality and loaded-content contrast remain unverified. This is a focused source audit, not a declaration of WCAG compliance or a complete global-standards certification.

Once a working development database is available, review at 320, 375, 768, 1024 and 1440px, at 200% zoom and with keyboard-only navigation. Test long CMS hero copy, many slides, and long menu labels; confirm no clipping or overlap. Test real contact, application, newsletter and donation journeys with their server responses. Verify eligibility, fees, locations, accessibility accommodations and next steps are stated in actual programme content. Preserve real participant stories and consented imagery, and avoid unsupported claims.

Further work: unify the large legacy stylesheet gradually; review dynamically supplied image descriptions, modal focus behavior, CMS menu duplication, CMS colour settings and performance using real content. The generic newsletter helper now submits only through a real configured form action and no longer fabricates success; actual subscription delivery still requires end-to-end verification. Public forms and payments were not submitted during this audit. Root independently repeated PHP and JavaScript syntax checks and exercised amount selection, clipboard fallback and newsletter backend-action behavior in a JavaScript VM; those checks passed.
