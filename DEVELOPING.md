# Development Guidelines

This document describes tools, tasks and workflow that one needs to be familiar with in order to effectively maintain
this project. If you use this package within your own software as is but don't plan on modifying it, this guide is
**not** for you.

## Tools

*  [Phing](http://www.phing.info/): used to run predefined tasks. Installed via Composer into the vendor directory. You
   can run phing but using the command line script `./vendor/bin/phing` or you can put it on your PATH.
*  [Composer](https://getcomposer.org/): used to manage dependencies for the project.
*  [Box](http://box-project.org/): used to generate a phar archive, which is useful for users who
   don't use Composer in their own project.

## Tasks

### Testing

This project's tests are written as PHPUnit test cases. Common tasks:

*  `./vendor/bin/phing test` - run the test suite.

### Releasing

In order to create a release, the following should be completed in order.

1. Ensure all the tests are passing (`./vendor/bin/phing test`) and that there is enough test coverage.
1. Make sure you are on the `master` branch of the repository, with all changes merged/commited already.
1. Update the version number in the source code and the README. See [Versioning](#versioning) for information
   about selecting an appropriate version number. Files to inspect for possible need to change:
   - src/OpenTok/Util/Client.php
   - tests/OpenTok/OpenTokTest.php
   - tests/OpenTok/ArchiveTest.php
   - README.md (only needs to change when MINOR version is changing)
1. Commit the version number change with the message "Update to version x.x.x", substituting the new version number.
1. Create a git tag: `git tag -a vx.x.x -m "Release vx.x.x"`
1. Change the version number for future development by incrementing the PATH number and adding
   "-alpha.1" in each file except samples and documentation. Then make another commit with the
   message "Begin development on next version".
1. Push the changes to the source repository: `git push origin master; git push --tags origin`s
1. Generate a phar archive for distribution using [Box](https://github.com/box-project/box2): `box build`. Be sure that the
   dependencies in the `/vendor` directory are current before building. Upload it to the GitHub Release. Add
   release notes with a description of changes and fixes.

## Workflow

### Versioning

The project uses [semantic versioning](http://semver.org/) as a policy for incrementing version numbers. For planned
work that will go into a future version, there should be a Milestone created in the Github Issues named with the version
number (e.g. "v2.2.1").

During development the version number should end in "-alpha.x" or "-beta.x", where x is an increasing number starting from 1.

### Branches

*  `master` - the main development branch.
*  `feat.foo` - feature branches. these are used for longer running tasks that cannot be accomplished in one commit.
   once merged into master, these branches should be deleted.
*  `vx.x.x` - if development for a future version/milestone has begun while master is working towards a sooner
   release, this is the naming scheme for that branch. once merged into master, these branches should be deleted.

### Tags

*  `vx.x.x` - commits are tagged with a final version number during release.

### Issues

Issues are labelled to help track their progress within the pipeline.

*  no label - these issues have not been triaged.
*  `bug` - confirmed bug. aim to have a test case that reproduces the defect.
*  `enhancement` - contains details/discussion of a new feature. it may not yet be approved or placed into a
   release/milestone.
*  `wontfix` - closed issues that were never addressed.
*  `duplicate` - closed issue that is the same to another referenced issue.
*  `question` - purely for discussion

### Management

When in doubt, find the maintainers and ask.
