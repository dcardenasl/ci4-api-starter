# Release v1.x.x — 🚀 Enhanced Setup Workflow & Security Hardening

This release significantly improves the Developer Experience (DX) by streamlining and securing the initial API configuration process.

### 🌟 New Features
- **Superadmin Auto-provisioning**: You can now configure the initial Superadmin account directly during the `init` phase, reducing manual post-installation steps.
- **Unified Entrypoint**: The setup process has been simplified around `init.sh`, providing a clearer path for new contributors.

### 🛠️ Improvements & Fixes
- **Hardened Scripts**: Enhanced intelligence in `install.sh` for detecting Docker environments and validating database credentials before attempting migrations.
- **Better Diagnostics**: Improved error reporting during the bootstrap process to help identify environment issues quickly.
- **Legacy Cleanup**: Removed deprecated `setup-env.sh` to maintain a clean and focused codebase.

### 📖 Documentation
- Updated setup guides and READMEs to align with the new installation standards and security best practices.
