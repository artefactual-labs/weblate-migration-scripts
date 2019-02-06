# weblate-migration-scripts

This repository contains scripts to migrate translations to and from
Artefactual projects (currently only AtoM).

The **Quick start** section provides basic setup and usage information while
subsequent sections go into more detail about what import and export actually
do to the XLIFF data and automation of import and export.

Examples in this README file use the `$` character to indicate a command-line
prompt.


Quick start
-----------

1) First use Vagrant to [create an AtoM virtual machine][1] if you haven't already:

[1]:https://www.accesstomemory.org/en/docs/2.5/dev-manual/env/vagrant/#dev-env-vagrant 

2) SSH into the Vagrant box.

For example:

    $ ssh -p 2222 vagrant@127.0.0.1

Before proceeding be sure to add your public/private keys (if they aren't
already added) if you'll be exporting new translations from the Weblate AtoM
repo to the internal AtoM repository.

Also make sure your Git configuration specifies your name and email.

For example:

    $ git config --global user.name "Bob Example"
    $ git config --global user.email "bob@example.com"

3) Run the automatic setup script to clone the necessary repos.

The Quick automatic setup will clone the AtoM, Weblate AtoM translations, and
Weblate migration script repos into a subdirectory of the vagrant user's  home
directory. The quick start examples assume the "translate" subdirectory is
being used.

Run the automatic setup script from any directory:

    $ source <(curl -L -s https://bit.ly/2RxFp4X)

The default branch in the AtoM repository will be used to determine the
appropriate branch to change to in the Weblate AtoM translations repository:
branch 2.5.x, for example, if the default AtoM repository branch is qa/2.5.x.

4) Import or export translation data:

Following are the commands to import from AtoM (new source strings
will automatically be extracted from AtoM's source code) or export from
the Weblate AtoM translations repository to AtoM.

Import into the Weblate AtoM translations repository from AtoM:

    $ cd $HOME/translate/weblate-migration-scripts
    $ ./import_from_atom --atom-dir="$HOME/translate/atom" \
      --weblate-dir="$HOME/translate/atom-translations"

Export from the Weblate AtoM translation repository to AtoM:

    $ cd $HOME/translate/weblate-migration-scripts 
    $ ./export_to_atom --approved --atom-dir="$HOME/translate/atom" \
      --weblate-dir="$HOME/translate/atom-translations"


Manual setup
------------

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

Example manual setup:

    $ cd $HOME
    $ git clone https://github.com/artefactual-labs/weblate-migration-scripts
    $ git clone https://github.com/artefactual/atom-translations
    $ git clone https://github.com/artefactual/atom # Change to your AtoM repo
    $ cd atom
    $ git checkout -b qa/2.5.x origin/qa/2.5.x
    $ cd $HOME/atom-translations
    $ git checkout -b 2.5.x origin/2.5.x



Importing from the AtoM repo into the Weblate AtoM translations repo
--------------------------------------------------------------------

To update AtoM's XLIFF files with any new source messages (extracting them from
source code), and copy the resulting XLIFF files to the `i18n` subdirectory in
the Weblate AtoM translations repo, use the `import_from_atom` script.

Example import:

    $ cd $HOME/weblate-migration-scripts
    $ ./import_from_atom --atom-dir="$HOME/atom" --weblate-dir="$HOME/atom-translations"

In addition to copying the XLIFF files it will also process them so they're
suitable for Weblate (among other things, adding "approved" and "translated"
attributes to each XLIFF translation unit that's already been translated).

The script will then offer to Git commit the XLIFF files in the i18n
subdirectory of the Weblate AtoM translations repo and Git push the changes.

The `--no-pull` option can be used to stop the script from prompting to do a
git pull (useful for automating XLIFF import into Weblate AtoM translations
repo).

The `--no-push` option can be used to stop the script from attempting to
commit and push.

The `--commit-with-message` option can be used to automatically commit any
changes (with the commit message specified as a value for the option).

Example automated import:

    $ cd $HOME/weblate-migration-scripts
    $ ./import_from_atom --atom-dir="$HOME/atom" \
      --weblate-dir="$HOME/atom-translations" \
      --commit-with-message="Imported new translations from AtoM" --no-pull

If you just want to import a single language from AtoM use the `--language`
option. For example: `--language="ca"`.


Exporting from the Weblate AtoM translations repo to the AtoM repo
------------------------------------------------------------------

To copy translation units marked "approved" from XLIFF files in the Weblate
AtoM translations repo to AtoM's `apps/qubit/i18n` directory, execute the
`export_to_atom` script using the `--approved` flag.

Example export:

    $ cd $HOME/weblate-migration-scripts 
    $ ./export_to_atom --approved --atom-dir="$HOME/atom" --weblate-dir="$HOME/atom-translations"

If the `--approved` flag isn't set all translation units will be exported to
AtoM (used for testing out unapproved translations).

In addition to copying the XLIFF files it will also process them so they're 
suitable for AtoM (among other things, removing the "approved" and "translated" 
attributes for each XLIFF translation unit that's been translated).

The script will then offer to Git commit the XLIFF files in the
`apps/qubit/i18n` subdirectory of the AtoM repo and Git push the changes.

The `--no-pull` option can be used to stop the script from prompting to do a
git pull.

The `--no-push` option can be used to stop the script from attempting to
commit and push.

The `--commit-with-message` option can be used to automatically commit any
changes (with the commit message specified as a value for the option).

Example automated export:

    $ cd $HOME/weblate-migration-scripts
    $ ./export_to_atom --approved --atom-dir="$HOME/atom" \
      --weblate-dir="$HOME/atom-translations" \
      --commit-with-message="Imported new translations from Weblate" --no-pull

If you just want to export a single language from AtoM use the `--language` 
option. For example: `--language="ca"`.


Approving all translation units in an XLIFF file
------------------------------------------------

Run script to mark all translation units in one or more XLIFF file(s) as `approved`
and `translated`.

Example:

    $ cd $HOME/weblate-migration-scripts
    $ ./scripts/approve i18n

The `--language` option can be used to approve only translation units for a
specific language.


Comparing a Weblate XLIFF file with an AtoM XLIFF file
------------------------------------------------------

The `compare` script will compare the translation units in one or more
AtoM-formatted XLIFF files with their corresponding Weblate-formatted XLIFF
files and will specify, via output and the exit code (set to 1 if a difference
has been detected), whether or not a difference has been detected.

During comparison all non-approved translation units in the Weblate XLIFF are
ignored if the `--approved` flag is set.

Example:

    $ cd $HOME/weblate-migration-scripts
    $ ./scripts/compare --language="pl" --approved $HOME/translate/atom/apps/qubit/i18n \
                        $HOME/translate/atom-translations/i18n

Note: In order to simplify comparision, XLIFF content is summarized as JSON and
these JSON files are put into the temp directory. If the `--debug` option is set
then these files won't be deleted and can be examined.
