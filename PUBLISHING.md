# Publishing to Packagist

This guide explains how to publish the SeAT Member Engagement Module to Packagist so it's discoverable via Composer.

## Prerequisites

- GitHub account (already have repo)
- Packagist account (free)
- Git with tags/releases capability
- **SeAT 5.x** (Laravel 10) installation for compatibility
- **PHP 8.1+**

## Step 1: Create a GitHub Release

**Tag without a `v` prefix.** SeAT's settings page renders the installed
version as `v{getVersion()}`, and `getVersion()` is `final` in
`AbstractSeatPlugin` - it returns Composer's pretty version, which is the
literal tag string. A tag of `v8.18.0` therefore displays as `vv8.18.0`,
and no code in this package can change that. Every other SeAT plugin tags
bare (`3.0.2`, `2.0.5`), which is why they render correctly.

Tags up to `v8.18.0` carry the old prefix; from `8.18.1` onwards they don't.
Composer normalises both, so ordering across the change is still correct.

Create a semantic version tag for your first release:

```bash
# Create and push a git tag
git tag 1.0.0
git push origin 1.0.0
```

Or create via GitHub UI:
1. Go to: https://github.com/rational-chaos-inc/seat-mem/releases
2. Click "Create a new release"
3. Tag: `v1.0.0`
4. Title: `Initial Release`
5. Description: Copy from README.md features
6. Click "Publish release"

## Step 2: Register on Packagist

1. Visit: https://packagist.org
2. Click "Sign up" or "Sign in with GitHub"
3. Authorize GitHub access
4. After login, click "Submit a Package"
5. Enter repository URL: `https://github.com/rational-chaos-inc/seat-mem.git`
6. Click "Check"
7. Click "Submit"

## Step 3: Enable Auto-Update (GitHub Hook)

To automatically update Packagist when you push tags:

1. Go to: https://packagist.org/packages/rci/member-engagement
2. Click "Settings" (top right)
3. Under "Update Strategy", enable "GitHub Service Hook"
4. Or manually visit: https://packagist.org/api/update-package?username=YOUR_USERNAME&name=rci/member-engagement

## Verification

After publishing, anyone can install via:

```bash
composer require rci/member-engagement
```

## Version Management

For future updates:

```bash
# For minor feature updates
git tag 1.1.0
git push origin 1.1.0

# For bug fixes
git tag 1.0.1
git push origin 1.0.1

# For major breaking changes
git tag 2.0.0
git push origin 2.0.0
```

Packagist will automatically detect new tags and update the package listing.

## Semantic Versioning

Use semantic versioning (MAJOR.MINOR.PATCH):
- **MAJOR**: Breaking changes
- **MINOR**: New features (backwards compatible)
- **PATCH**: Bug fixes

Example progression:
- v1.0.0 - Initial release
- v1.1.0 - Added alerts feature
- v1.1.1 - Fixed dashboard bug
- v2.0.0 - Removed deprecated API endpoints

## References

- Packagist: https://packagist.org
- Semantic Versioning: https://semver.org
- Composer Documentation: https://getcomposer.org/doc
- GitHub Releases: https://docs.github.com/en/repositories/releasing-projects-on-github
