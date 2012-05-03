## v1.2.0

- Restore default blocks on overwrite [#20][20]
- Correctly handles 1.9 backups [#20][20]
- Gracefully skips non-existent filters and blocks [#20][20]

[20]: https://github.com/lsuits/simple_restore/issues/20

## v1.1.1

- Minor bug in course variable name for course summary
- Use correct temp directory $CFG setting
- Bug in backadel integration on older systems

## v1.1.0

- Fixes minor bug in table building [8bf4eb6](https://github.com/lsuits/simple_restore/commit/8bf4eb6bcf7234c02d43074156ec2e399d2224ca)
- Added admin options: Overwrite course conifg, keep enrollments, keep groups [#17](https://github.com/lsuits/simple_restore/issues/17)
- Added events on selection and restore [#7](https://github.com/lsuits/simple_restore/issues/7)

## v1.0.0

- Initial Public Release
- Features:
  - Overwrite the current course with materials from previously taught courses
  - Import old matierals into the current course from previously taught courses
  - Integrates with LSU's Baclup and Delete (backadel) for seamless end of semester backups
