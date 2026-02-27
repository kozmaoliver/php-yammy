# Yammy - Secure PHP Package Manager

**Yammy** is a security-first PHP package manager that prioritizes integrity verification and safe package installation. Unlike traditional package managers, Yammy uses a quarantine system and cryptographic hash verification to ensure packages are never executed before being verified as safe.

## Quick Start

### Installation

```bash
# Add yammy to your PATH
export PATH="$PATH:/path/to/yammy/bin"

# Or create symlink
ln -s /path/to/yammy/bin/yammy /usr/local/bin/yammy
```

### Create Project

make a yammy.yaml file:
```yaml
name: my-app
require:
  vendor/package: 1.2.3
packages:
  vendor/package:
    src: "https://github.com/vendor/package"
    hash: "ABC123DEF456"
```

```bash
# Install packages
yammy install
```

### Verify Integrity

```bash
# Check all packages
yammy check-integrity

# Generate hash for new package
yammy generate-hash ./yammies/my-package
```

---

## Commands

### `yammy install`
Install all packages defined in `yammy.yaml`

**Security features:**
- Downloads to quarantine first
- Verifies hash before moving to production
- Fails fast on integrity violations
- Keeps compromised packages isolated

**Example:**
```bash
$ yammy install
Starting package installation...

Downloading vendor/package to quarantine...
Downloaded from https://github.com/vendor/package
Verifying package integrity...
Hash verification passed
Installed vendor/package (1.2.3) from https://github.com/vendor/package

Lock file saved: yammy.lock
Installation complete!
```

### `yammy check-integrity`
Verify integrity of installed packages

**Use cases:**
- Post-deployment verification
- Detect tampering
- CI/CD security gates
- Regular audits

**Example:**
```bash
$ yammy check-integrity
Checking package integrity...

vendor/package: integrity OK
vendor/other: no hash specified
   Current hash: DEF789GHI012

All packages passed integrity check
```

### `yammy generate-hash <directory>`
Generate hash for a package

**Usage:**
```bash
# Generate hash for current directory
yammy generate-hash .

# Generate hash for specific package
yammy generate-hash ./yammies/vendor/package

# Output: ABC123DEF456
```

### `yammy clean-quarantine`
Remove all packages from quarantine

**When to use:**
- After reviewing failed installations
- Cleanup disk space
- Before fresh install

**Example:**
```bash
$ yammy clean-quarantine
Removed: my-package_1708789200
Cleaned 1 quarantined package(s)
```

### `yammy help`
Display help and usage information

---

## Configuration (yammy.yaml)

### Basic Structure

```yaml
name: my-application
description: My secure PHP application

# Dependencies
require:
  vendor/package: 1.2.3
  another/lib: 2.0.0

# Package sources with hashes
packages:
  vendor/package:
    src: "https://github.com/vendor/package"
    hash: "ABC123DEF456"
    
  another/lib:
    src: "git@github.com:another/lib.git"
    hash: "789GHI012JKL"

# Security settings
security:
  require-hashes: true
  strict-mode: true
```

### Field Reference

| Field                     | Required | Description                                   |
|---------------------------|----------|-----------------------------------------------|
| `name`                    | ✅        | Project name                                  |
| `description`             | ❌        | Project description                           |
| `require`                 | ❌        | Package dependencies (name: version)          |
| `packages.<name>.src`     | ✅        | Git repository URL                            |
| `packages.<name>.hash`    | ⚠️       | Package integrity hash (strongly recommended) |
| `security.require-hashes` | ❌        | Enforce hash requirement (default: true)      |
| `security.strict-mode`    | ❌        | Fail on any security issue (default: true)    |

---

## Security Model

### Quarantine System

```
Download → Quarantine → Verify Hash → Move to Production
                ↓ (if failed)
          Stay in .quarantine/
```

**Key principles:**
1. Never execute untrusted code
2. Verify before trust
3. Fail securely (keep evidence)

### Hash Verification

**What's hashed:**
- All `.php`, `.phtml`, `.html`, `.js`, `.yaml` files
- Recursive through directories
- Excludes: `.git/`, `yammy.lock`, symlinks

**Algorithm:**
- xxHash64 (fast, collision-resistant)
- Can be upgraded to SHA-256 in future

**Verification flow:**
```php
1. Download package to quarantine
2. Compute actual hash
3. Compare with expected hash
4. if (match) → Move to production
   else       → Keep in quarantine, alert user
```

### Security Guarantees

✅ **Integrity**: Cryptographic hash verification  
✅ **Isolation**: Quarantine system prevents execution of unverified code  
✅ **Auditability**: Security logs track all operations  
✅ **Validation**: All inputs sanitized and validated  
✅ **Safe Defaults**: Hash verification required by default  

See [SECURITY.md](SECURITY.md) for complete security documentation.

---

## Advanced Usage

### CI/CD Integration

```bash
# .github/workflows/deploy.yml
- name: Install dependencies
  run: yammy install
  env:
    YAMMY_AUTO_APPROVE: 1  # Skip interactive prompts

- name: Verify integrity
  run: yammy check-integrity
```

### Docker Integration

```dockerfile
FROM php:8.1-cli

# Install YAML extension
RUN pecl install yaml && docker-php-ext-enable yaml

# Copy yammy
COPY yammy /opt/yammy
ENV PATH="/opt/yammy/bin:$PATH"

# Install dependencies
WORKDIR /app
COPY yammy.yaml .
RUN yammy install
```

### Pre-commit Hook

```bash
#!/bin/bash
# .git/hooks/pre-commit

echo "Checking package integrity..."
yammy check-integrity

if [ $? -ne 0 ]; then
    echo "Integrity check failed!"
    exit 1
fi
```

### Monorepo Setup

```yaml
# Root yammy.yaml
name: monorepo
require:
  shared/lib: 1.0.0

packages:
  shared/lib:
    src: "./packages/shared-lib"  # Local path
    hash: "ABC123"
```

---

## 🎯 Comparison with Composer

| Feature              | Yammy                 | Composer                     |
|----------------------|-----------------------|------------------------------|
| Hash Verification    | ✅ Required by default | ⚠️ Optional (composer.lock)  |
| Quarantine System    | ✅ Yes                 | ❌ No                         |
| Security Logging     | ✅ Yes                 | ❌ No                         |
| Input Validation     | ✅ Strict              | ⚠️ Basic                     |
| Integrity Commands   | ✅ `check-integrity`   | ⚠️ `validate` (partial)      |
| Pre-execution Checks | ✅ Yes                 | ❌ No                         |
| Audit Trail          | ✅ yammy-security.log  | ❌ No                         |

**When to use Yammy:**
- High-security environments
- Financial/healthcare applications
- Regulated industries
- Air-gapped deployments
- Supply chain security critical

**When to use Composer:**
- Rapid prototyping
- Large existing ecosystem needed
- Legacy projects

---

## Troubleshooting

### "YAML extension is required"

```bash
# Ubuntu/Debian
sudo apt-get install php-yaml

# macOS
brew install php
pecl install yaml

# From source
pecl install yaml
echo "extension=yaml.so" >> /etc/php/conf.d/yaml.ini
```

### "Hash mismatch" error

1. **Expected:** Package was updated upstream
   ```bash
   # Regenerate hash
   yammy generate-hash ./yammies/.quarantine/package-name_timestamp
   # Update yammy.yaml with new hash
   ```

2. **Unexpected:** Possible compromise
   ```bash
   # DO NOT USE THE PACKAGE
   # Investigate quarantined package
   ls -la ./yammies/.quarantine/
   # Report to package maintainer
   ```

### Package stuck in quarantine

```bash
# Review the package
ls -la ./yammies/.quarantine/

# If safe, update hash in yammy.yaml
yammy generate-hash ./yammies/.quarantine/package_timestamp

# Clean up
yammy clean-quarantine
```

---

## Examples

### Example 1: Basic Project

```yaml
# yammy.yaml
name: my-blog
require:
  yammy/hello: 1.1.0
packages:
  yammy/hello:
    src: "https://github.com/kozmaoliver/php-yammy-hello"
    hash: "08C09C29BAD34234"
```

```bash
$ yammy install
Installation complete!

$ yammy check-integrity
All packages passed integrity check
```

### Example 2: Multi-Package Project

```yaml
name: ecommerce-platform
require:
  payment/stripe: 3.0.0
  auth/oauth: 2.5.1
  db/query-builder: 1.8.0

packages:
  payment/stripe:
    src: "https://github.com/stripe/stripe-php"
    hash: "A1B2C3D4E5F6"
  
  auth/oauth:
    src: "https://github.com/thephpleague/oauth2-client"
    hash: "F6E5D4C3B2A1"
  
  db/query-builder:
    src: "https://github.com/doctrine/dbal"
    hash: "1A2B3C4D5E6F"
```

### Example 3: Private Repositories

```yaml
name: enterprise-app
require:
  company/internal-lib: 2.0.0

packages:
  company/internal-lib:
    src: "git@github.com:company/internal-lib.git"
    hash: "PRIVATE_HASH_123"
```

**Note:** Ensure SSH keys are configured for private repos:
```bash
ssh-add ~/.ssh/id_rsa
yammy install
```

---

## Contributing

We welcome contributions! Please see:
- [SECURITY.md](SECURITY.md) - Security model and reporting

### Reporting Security Issues

**DO NOT** open public issues for security vulnerabilities.

Report to: [security@example.com]

---

## License

MIT License - See LICENSE file

---

## Credits

Inspired by:
- **Go modules** - Cryptographic verification
- **Nix** - Content-addressed storage
- **Cargo** - Secure package management
- **Composer** - PHP ecosystem

Built with ❤️ for the PHP security community.

---

**Made for security. Built for developers. Trusted for production.** 🔒
