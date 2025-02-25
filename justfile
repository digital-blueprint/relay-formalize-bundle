# Use `just <recipe>` to run a recipe
# https://just.systems/man/en/

# By default, run the `--list` command
default:
    @just --list

# Run the linter
[group('dev')]
lint:
    nix-shell -p php83Packages.composer --run "composer run lint"

# Run the linting, like in CI
[group('dev')]
linting:
    nix-shell -p php83Packages.composer --run "composer run cs"
    composer run phpstan
    composer run psalm
    nix-shell -p composer-require-checker --run "composer-require-checker"

# Format all justfiles
[group('linter')]
just-format:
    #!/usr/bin/env bash
    # Find all files named "justfile" recursively and run just --fmt --unstable on them
    find . -type f -name "justfile" -print0 | while IFS= read -r -d '' file; do
        echo "Formatting $file"
        just --fmt --unstable -f "$file"
    done
