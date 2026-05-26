## What & why / ماذا ولماذا
<!-- Describe the change and the motivation. -->

## Related issue / المشكلة المرتبطة
<!-- e.g. Closes #123 -->

## Checklist / قائمة التحقّق
- [ ] No secrets committed (`config.local.php` untouched)
- [ ] All output escaped with `e()`
- [ ] State‑changing endpoints protected with CSRF
- [ ] `php -l` passes on all changed PHP files
- [ ] Tested in both languages (`?lang=ar` & `?lang=en`)
- [ ] New UI strings added to `includes/i18n.php` (ar + en)
- [ ] No new dependencies / no build step introduced
