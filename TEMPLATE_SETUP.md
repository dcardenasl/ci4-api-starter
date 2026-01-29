# GitHub Template Repository Setup

This guide explains how to configure this repository as a GitHub Template Repository, allowing users to easily create new projects based on it.

## What is a Template Repository?

A GitHub Template Repository allows users to generate new repositories with the same directory structure and files, but without the original commit history. This is perfect for starter kits and boilerplates.

## Setting Up as Template (One-Time Setup)

### Option 1: Via GitHub Web Interface

1. **Navigate to your repository** on GitHub
   - Go to `https://github.com/YOUR-USERNAME/ci4-api-starter`

2. **Access Repository Settings**
   - Click on **Settings** (gear icon in the top-right)
   - Must be repository owner or have admin permissions

3. **Enable Template Repository**
   - Scroll down to the **"Template repository"** section
   - Check the box **"Template repository"**
   - GitHub will show: ✓ Template repository enabled

4. **Save Changes**
   - Changes are saved automatically
   - You'll see a new "Use this template" button appear on your repository page

### Option 2: Via GitHub CLI

```bash
# Install GitHub CLI if not already installed
# https://cli.github.com/

# Authenticate
gh auth login

# Mark repository as template
gh repo edit YOUR-USERNAME/ci4-api-starter --template
```

## Using the Template (For Users)

Once configured as a template, users can create new projects:

### Via GitHub Web Interface

1. Visit the template repository
2. Click the green **"Use this template"** button
3. Select **"Create a new repository"**
4. Enter:
   - Repository name (e.g., `my-api-project`)
   - Description
   - Public/Private visibility
5. Click **"Create repository"**
6. Clone the new repository:
   ```bash
   git clone https://github.com/YOUR-USERNAME/my-api-project.git
   cd my-api-project
   ./init.sh
   ```

### Via GitHub CLI

```bash
# Create new repository from template
gh repo create my-api-project \
  --template YOUR-USERNAME/ci4-api-starter \
  --public \
  --clone

# Navigate and initialize
cd my-api-project
./init.sh
```

## Benefits of Template Repositories

### For Template Maintainers
- ✅ Users always get the latest version
- ✅ Easy to update and improve
- ✅ Clear separation between template and projects
- ✅ Can track usage via GitHub insights

### For Users
- ✅ Clean repository without template history
- ✅ One-click project creation
- ✅ No need to manually clean up git history
- ✅ Faster than cloning and cleaning

## Template Best Practices

### 1. Keep README User-Focused

The README should explain:
- What the template provides
- How to use it
- Quick start instructions
- Where to find detailed documentation

### 2. Include Initialization Script

The `init.sh` script automates:
- Dependency installation
- Environment configuration
- Key generation
- Database setup

### 3. Provide Clear Documentation

Separate concerns:
- `README.md` - Getting started guide
- `DEVELOPMENT.md` - Architecture and patterns
- `TEMPLATE_SETUP.md` - This file
- `SECURITY.md` - Security guidelines

### 4. Use Example Files

Provide `.example` files for sensitive configuration:
- `.env.example` ✅ (tracked)
- `.env` ❌ (ignored)
- `.env.docker.example` ✅ (tracked)
- `.env.docker` ❌ (ignored)

### 5. Keep .gitignore Updated

Ensure the template ignores:
```gitignore
.env
.env.docker
vendor/
writable/cache/*
writable/logs/*
writable/session/*
writable/uploads/*
```

## Customization Checklist for New Projects

After creating a project from this template, users should:

- [ ] Run `./init.sh` to set up the project
- [ ] Update `composer.json` (name, description, authors)
- [ ] Update `app/Config/OpenApi.php` (API title, description, version)
- [ ] Generate secure keys (done by init.sh)
- [ ] Customize database schema (add migrations)
- [ ] Add project-specific resources
- [ ] Update README.md with project details
- [ ] Configure CI/CD for new repository
- [ ] Set up deployment pipelines

## Maintaining the Template

### Updating the Template

When you improve the template:

1. **Make changes** in the template repository
2. **Test thoroughly** to ensure everything works
3. **Update version** in CHANGELOG.md (if you keep one)
4. **Document changes** in README.md
5. **Commit and push** to main branch

Users creating new projects will automatically get the latest version.

### Versioning Strategy (Optional)

You can tag releases for major updates:

```bash
git tag -a v1.0.0 -m "Initial stable release"
git push origin v1.0.0

git tag -a v2.0.0 -m "Added modular OpenAPI documentation"
git push origin v2.0.0
```

Users can then specify which version to use when creating projects.

## Troubleshooting

### "Use this template" button not visible

**Possible causes:**
- Repository is not marked as template (check Settings)
- You're viewing a fork, not the original template
- Browser cache issue (hard refresh with Ctrl+Shift+R)

**Solution:**
1. Go to Settings → Template repository
2. Ensure checkbox is enabled
3. Refresh repository page

### Template includes commit history

**This shouldn't happen with GitHub templates.**

If users see your commit history:
- They cloned instead of using template
- They should use "Use this template" button instead

### Can't enable template on forked repository

GitHub limitation: You cannot mark a fork as a template.

**Workaround:**
1. Create a new repository (not fork)
2. Push code from the fork to new repository
3. Mark new repository as template

## Additional Resources

- [GitHub Templates Documentation](https://docs.github.com/en/repositories/creating-and-managing-repositories/creating-a-template-repository)
- [Repository Settings Guide](https://docs.github.com/en/repositories/managing-your-repositorys-settings-and-features)
- [GitHub CLI Reference](https://cli.github.com/manual/gh_repo_create)

---

**Note:** This setup is only needed once by the template maintainer. End users simply click "Use this template" to create their projects.
