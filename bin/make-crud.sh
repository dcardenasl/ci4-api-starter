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

# Parse arguments
RESOURCE=${1:-}
DOMAIN=${2:-}
FIELDS=${3:-}
SOFT_DELETE=${4:-yes}
ROUTE=${5:-}

# Validate arguments
if [[ -z "$RESOURCE" || -z "$DOMAIN" || -z "$FIELDS" ]]; then
    echo -e "${RED}❌ Missing arguments${NC}"
    echo ""
    echo "Usage:"
    echo "  ./bin/make-crud.sh <Resource> <Domain> <Fields> [SoftDelete] [Route]"
    echo ""
    echo "Examples:"
    echo "  ./bin/make-crud.sh Audience Shows 'name:string:required|searchable' no"
    echo "  ./bin/make-crud.sh Company Shows 'name:string:required|searchable,description:text' yes"
    echo "  ./bin/make-crud.sh UpaEvent Events 'title:string:required|searchable,year:int' yes upa-events"
    echo ""
    echo "Arguments:"
    echo "  <Resource>     - Resource name in StudlyCase (e.g., Audience, SchoolCategory)"
    echo "  <Domain>       - Domain folder name (e.g., Shows, Education, Media)"
    echo "  <Fields>       - Comma-separated fields: name:type:options (use single quotes!)"
    echo "  [SoftDelete]   - yes or no (default: yes)"
    echo "  [Route]        - Custom route name (default: pluralized resource name)"
    echo ""
    echo "Field Options:"
    echo "  required       - Field is required"
    echo "  nullable       - Field can be null"
    echo "  searchable     - Field is searchable"
    echo "  filterable     - Field is filterable"
    echo "  fk:table_name  - Foreign key reference"
    exit 1
fi

# Validate soft-delete value
if [[ "$SOFT_DELETE" != "yes" && "$SOFT_DELETE" != "no" ]]; then
    echo -e "${RED}❌ SoftDelete must be 'yes' or 'no'${NC}"
    exit 1
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

# Prepare route flag if provided
ROUTE_FLAG=""
if [[ -n "$ROUTE" ]]; then
    ROUTE_FLAG="--route $ROUTE"
fi

if php spark make:crud "$RESOURCE" \
    --domain "$DOMAIN" \
    --fields "$FIELDS" \
    --soft-delete "$SOFT_DELETE" \
    $ROUTE_FLAG 2>&1 | grep -E "CREATED|WIRING|✅"; then
    echo -e "${GREEN}✓ Scaffolding complete${NC}"
else
    echo -e "${RED}✗ Scaffolding failed${NC}"
    exit 1
fi

echo ""
echo -e "${YELLOW}Step 2: Auto-fixing code style...${NC}"
if composer cs-fix --quiet >/dev/null 2>&1; then
    echo -e "${GREEN}✓ Code style fixed${NC}"
else
    echo -e "${YELLOW}⚠ Code style check skipped${NC}"
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
echo -e "  6. Test API: ${BLUE}php spark serve${NC} (if not already running)"
echo -e "     Then: ${BLUE}curl -s http://localhost:8080/api/v1/${DOMAIN_LOWER}/${RESOURCE_PLURAL}${NC}"
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
