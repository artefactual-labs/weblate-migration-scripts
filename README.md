# weblate-migration-scripts

This repository contains scripts to migrate translations to and from
Artefactual projects (currently only AtoM).


Setup
-----

Clone this repo to the a filesystem where you also have the AtoM repo, and the
repo containing project translations, cloned somewhere.

If you'd like scripts supplied in this repo to suggest commit messages, copy
`git_hooks/prepare-commit-msg` into `.git/hooks` (or create a symlink to it
naming the symlink `prepare-commit-msg`). Do this in both the AtoM repo and the
repo containing project translations.

**Make sure** to have your AtoM repo switched to the branch appropriate for the
AtoM version you wish to work with.

Similarly, in the repo containing project translations, change to the branch
corresponding to the AtoM version being translated.


Updating AtoM XLIFF files and copying them to the Weblate repo
--------------------------------------------------------------

To update AtoM's XLIFF files with any new source messages, extracting them from
source code, and copy the resulting XLIFF files to the Weblate repo's `i18n`
subdirectory, use the `import_from_atom` script.

Example:

    $ ./import_from_atom --atom-dir="/usr/share/nginx/atom" --weblate-dir="../weblate-xliff"

In addition to copying the XLIFF files it will also process them so they're
suitable for Weblate (among other things, adding "approved" and "translated"
attributes to each XLIFF translation unit that's already been translated).

The script will then offer to Git commit the XLIFF files in the i18n
subdirectory of the Weblate repo and Git push the changes.


Copy XLIFF files from Weblate repo into AtoM
--------------------------------------------

Run script to copy the XLIFF files from the Weblate repo into AtoM's
`apps/qubit/i18n` directory.

Example:

    ./export_to_atom /usr/share/nginx/atom --atom-dir="/usr/share/nginx/atom" --weblate-dir="../weblate-xliff"

In addition to copying the XLIFF files it will also process them so they're 
suitable for AtoM (among other things, removing "approved" and "translated" 
attributes for each XLIFF translation unit that's been translated).

The script will then offer to Git commit the XLIFF files in the
`apps/qubit/i18n` subdirectory of the AtoM repo and Git push the changes.
