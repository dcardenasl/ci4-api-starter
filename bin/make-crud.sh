#!/bin/bash
#
# CodeIgniter 4 CRUD Scaffolding Helper
# Wrapper around `php spark make:crud` with automatic escaping and validation
#
# Usage:
#   ./bin/make-crud.sh Audience Shows 'name:string:required|searchable' no
#   ./bin/make-crud.sh Company Shows 'name:string:required|searchable,description:text' yes
#   ./bin/make-crud.sh UpaEvent Events 'title:string:required|searchable,year:int' yes upa-events
#

set -e
set -o pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Parse arguments — interleaved positionals + flags.
POSITIONAL=()
MIGRATE=false
DRY_RUN=false
while [[ $# -gt 0 ]]; do
    case "$1" in
        --migrate)  MIGRATE=true; shift ;;
        --dry-run)  DRY_RUN=true; shift ;;
        --help|-h)
            cat <<'USAGE'
Usage:
  ./bin/make-crud.sh <Resource> <Domain> <Fields> [SoftDelete] [Route] [--migrate] [--dry-run]

Examples:
  ./bin/make-crud.sh Audience Shows 'name:string:required|searchable' no
  ./bin/make-crud.sh Company Shows 'name:string:required|searchable,description:text' yes
  ./bin/make-crud.sh UpaEvent Events 'title:string:required|searchable,year:int' yes upa-events
  ./bin/make-crud.sh Product Catalog 'name:string:required' yes --migrate

Arguments:
  <Resource>     - Resource name in StudlyCase (e.g., Audience, SchoolCategory)
  <Domain>       - Domain folder name (e.g., Shows, Education, Media)
  <Fields>       - Comma-separated fields: name:type:options (use single quotes!)
  [SoftDelete]   - yes or no (default: yes)
  [Route]        - Custom route slug (default: kebab-case plural of resource)

Flags:
  --migrate      - Run 'php spark migrate' after scaffolding and abort if it fails
                   (catches the upstream bug where spark migrate exits 0 on errors)
  --dry-run      - Show planned actions without writing files (delegates to make:crud --dry-run)

Field Options:
  required       - Field is required
  nullable       - Field can be null
  searchable     - Field is searchable
  filterable     - Field is filterable
  unique         - Field has a UNIQUE index
  fk:table_name  - Foreign key reference (validated against DB pre-write)
  fk:table_name:setnull|restrict|cascade - FK with explicit ON DELETE behavior
USAGE
            exit 0
            ;;
        --*)
            echo -e "${RED}❌ Unknown flag: $1${NC}"
            echo "Run with --help for usage."
            exit 1
            ;;
        *) POSITIONAL+=("$1"); shift ;;
    esac
done
set -- "${POSITIONAL[@]}"

RESOURCE=${1:-}
DOMAIN=${2:-}
FIELDS=${3:-}
SOFT_DELETE=${4:-yes}
ROUTE=${5:-}

# Validate arguments
if [[ -z "$RESOURCE" || -z "$DOMAIN" || -z "$FIELDS" ]]; then
    echo -e "${RED}❌ Missing arguments${NC}"
    echo "Run with --help for usage."
    exit 1
fi

# Validate soft-delete value
if [[ "$SOFT_DELETE" != "yes" && "$SOFT_DELETE" != "no" ]]; then
    echo -e "${RED}❌ SoftDelete must be 'yes' or 'no'${NC}"
    exit 1
fi

# Detect uppercase runs (acronyms) and warn about implicit normalization.
# This catches the audit P0 where 'APIKey' previously produced broken table/lang names.
if [[ "$RESOURCE" =~ [A-Z]{2,}[a-z] ]] || [[ "$RESOURCE" =~ [A-Z]{2,}$ ]]; then
    if command -v php >/dev/null 2>&1; then
        CANONICAL=$(php -r '
$v = $argv[1];
$v = preg_replace_callback("/([A-Z]+)([A-Z][a-z]|$)/", static fn (array $m): string => ucfirst(strtolower($m[1])) . $m[2], $v);
echo $v;
' -- "$RESOURCE")
        echo -e "${YELLOW}⚠ Resource '${RESOURCE}' contains a run of consecutive uppercase letters.${NC}"
        echo -e "${YELLOW}  Derived names will keep the acronym intact (snake='api_key' instead of 'a_p_i_key').${NC}"
        echo -e "${YELLOW}  If you prefer canonical StudlyCase, re-run with: ${CANONICAL}${NC}"
        echo ""
    fi
fi

echo -e "${BLUE}═══════════════════════════════════════════════════${NC}"
echo -e "${BLUE}CRUD Scaffolding Helper${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════${NC}"
echo ""
echo -e "${YELLOW}Configuration:${NC}"
echo "  Resource:     $RESOURCE"
echo "  Domain:       $DOMAIN"
echo "  Fields:       $FIELDS"
echo "  Soft Delete:  $SOFT_DELETE"
if [[ -n "$ROUTE" ]]; then
    echo "  Route:        $ROUTE"
fi
echo ""

# Change to project root
cd "$(dirname "$0")/.."

# Step 1: Run make:crud
echo -e "${YELLOW}Step 1: Scaffolding CRUD...${NC}"

# Prepare optional flags forwarded to spark.
SPARK_FLAGS=()
[[ -n "$ROUTE" ]] && SPARK_FLAGS+=(--route "$ROUTE")
[[ "$DRY_RUN" == true ]] && SPARK_FLAGS+=(--dry-run)

# Capture the full spark output to a tmpfile so that, on failure, we can show the user
# the exact error (previously the `grep -E` filter consumed errors silently).
SCAFFOLD_LOG=$(mktemp -t make-crud.XXXXXX)
trap 'rm -f "$SCAFFOLD_LOG"' EXIT

if php spark make:crud "$RESOURCE" \
    --domain "$DOMAIN" \
    --fields "$FIELDS" \
    --soft-delete "$SOFT_DELETE" \
    "${SPARK_FLAGS[@]}" > "$SCAFFOLD_LOG" 2>&1; then
    grep -E "CREATED|WIRING|✅|Would create|Would wire" "$SCAFFOLD_LOG" || true
    if [[ "$DRY_RUN" == true ]]; then
        echo -e "${GREEN}✓ Dry-run complete (no files written)${NC}"
        cat "$SCAFFOLD_LOG"
        exit 0
    fi
    echo -e "${GREEN}✓ Scaffolding complete${NC}"
else
    echo -e "${RED}✗ Scaffolding failed${NC}"
    echo -e "${YELLOW}--- spark output ---${NC}"
    cat "$SCAFFOLD_LOG"
    echo -e "${YELLOW}--- end spark output ---${NC}"
    exit 1
fi

echo ""
echo -e "${YELLOW}Step 2: Auto-fixing code style...${NC}"
if composer cs-fix --quiet >/dev/null 2>&1; then
    echo -e "${GREEN}✓ Code style fixed${NC}"
else
    echo -e "${YELLOW}⚠ Code style check skipped${NC}"
fi

# Optional Step 2b: --migrate runs and validates migrations.
# Necessary because `php spark migrate` returns exit 0 even when SQL fails — a
# pipeline relying solely on the exit code would silently skip the failure.
if [[ "$MIGRATE" == true ]]; then
    echo ""
    echo -e "${YELLOW}Step 2b: Running migrations (--migrate)...${NC}"
    MIGRATE_LOG=$(mktemp -t make-crud-migrate.XXXXXX)
    trap 'rm -f "$SCAFFOLD_LOG" "$MIGRATE_LOG"' EXIT
    php spark migrate --no-color > "$MIGRATE_LOG" 2>&1 || true
    # CI4 prints 'Migrations complete.' on success and stops printing on any
    # SQL/connection failure (while still exiting 0 — that's the upstream bug).
    # Treating the absence of that sentinel as failure is more reliable than
    # grepping error keywords (which match innocuous filenames like
    # CreateFailedJobsTable).
    if ! grep -qF 'Migrations complete.' "$MIGRATE_LOG"; then
        echo -e "${RED}✗ Migration failed (spark exits 0 even on SQL errors — see output below)${NC}"
        echo -e "${YELLOW}--- migrate output ---${NC}"
        cat "$MIGRATE_LOG"
        echo -e "${YELLOW}--- end migrate output ---${NC}"
        echo ""
        echo -e "${YELLOW}Tip: run 'php spark make:crud:remove ${RESOURCE} --domain ${DOMAIN}' to clean up, then fix the issue and retry.${NC}"
        exit 1
    fi
    grep -E 'Running|complete' "$MIGRATE_LOG" | tail -10 || true
    echo -e "${GREEN}✓ Migrations applied${NC}"
fi

# Compute pluralized snake_case resource name for the curl hint below.
# Handles: SchoolCategory -> school_categories, Company -> companies, Box -> boxes.
RESOURCE_PLURAL=$(php -r "
\$parts = preg_split('/(?=[A-Z])/', '$RESOURCE', -1, PREG_SPLIT_NO_EMPTY);
\$last = strtolower(array_pop(\$parts));
if (preg_match('/[^aeiou]y\$/', \$last)) {
    \$last = substr(\$last, 0, -1) . 'ies';
} elseif (preg_match('/(s|x|z|ch|sh)\$/', \$last)) {
    \$last .= 'es';
} else {
    \$last .= 's';
}
\$parts = array_map('strtolower', \$parts);
\$parts[] = \$last;
echo implode('_', \$parts);
")
DOMAIN_LOWER=$(echo "$DOMAIN" | tr '[:upper:]' '[:lower:]')

echo ""
echo -e "${YELLOW}Step 3: Next steps${NC}"
echo -e "  1. Review migration: ${BLUE}app/Database/Migrations/*_Create${RESOURCE}*Table.php${NC}"
echo "     - Verify table name is snake_case (e.g., school_categories)"
echo "     - If soft-delete='no', verify no 'deleted_at' field"
echo ""
echo -e "  2. Run migrations: ${BLUE}php spark migrate${NC}"
echo ""
echo "  3. **Restart server** to detect new route files:"
echo -e "     ${BLUE}pkill -f 'spark serve'; php spark serve --port 8080 &${NC}"
echo ""
echo -e "  4. Prepare test DB: ${BLUE}php spark tests:prepare-db${NC}"
echo ""
echo -e "  5. Run feature tests: ${BLUE}vendor/bin/phpunit tests/Feature/Controllers/${DOMAIN}/${RESOURCE}ControllerTest.php${NC}"
echo ""
# Custom route override wins; otherwise derive kebab from the snake plural.
ROUTE_KEBAB="${ROUTE:-${RESOURCE_PLURAL//_/-}}"
echo -e "  6. Test API: ${BLUE}php spark serve${NC} (if not already running)"
echo -e "     Then: ${BLUE}curl -s http://localhost:8080/api/v1/${DOMAIN_LOWER}/${ROUTE_KEBAB}${NC}"
echo ""
echo -e "  7. Generate Swagger: ${BLUE}php spark swagger:generate${NC}"
echo ""
echo -e "${YELLOW}⚠️  Important:${NC}"
echo "  - Code style is auto-fixed (Step 2), so git commit should pass on first try"
echo "  - Server must restart to detect new route files (Step 3)"
echo "  - Run validation commands above before committing"
echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════${NC}"
echo -e "${GREEN}✅ Scaffolding complete!${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════${NC}"
