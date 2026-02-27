# Yammy Package Creation Guide

## Required Files in Every Package

```
your-package/
├── yammy.yaml          # REQUIRED - Package manifest
├── YourClass.php       # Your code
├── README.md           # Recommended - Usage documentation
└── LICENSE             # Recommended - License file
```

---

## yammy.yaml - Required Fields

### Minimal Required Configuration

```yaml
name: vendor/package      # REQUIRED - Package name
```

### Recommended Configuration

```yaml
name: vendor/package      # Package identifier (vendor/name)
description: Short description of what your package does

# Optional metadata
author: Your Name
license: MIT
homepage: https://github.com/vendor/package

# Dependencies (if your package needs other packages)
require:
  another/package: 2.0.0
  vendor/library: ^1.5

# Autoload (for future autoloader feature)
autoload:
  psr-4:
    "Vendor\\Package\\": ""
```

---

## Versioning Best Practices

### Semantic Versioning (SemVer)

Format: `MAJOR.MINOR.PATCH`

```
1.0.0 → Initial release
1.0.1 → Bug fix (backwards compatible)
1.1.0 → New feature (backwards compatible)
2.0.0 → Breaking change (not backwards compatible)
```
---

## Complete Example: Creating yammy/hello

### Step 1: Create Package Structure

```bash
mkdir -p yammy-hello
cd yammy-hello
git init
```

### Step 2: Create yammy.yaml

```yaml
name: yammy/hello
description: Simple greeting package
author: Oliver Kozma
license: MIT
```

### Step 3: Create Your Code

```php
<?php
// Hello.php

namespace Yammy\Hello;

class Hello
{
    public static function greet(string $name = 'World'): string
    {
        return "Hello, {$name}! 👋";
    }
}
```

### Step 4: Generate Hash

```bash
# Navigate to parent directory
cd ..

# Generate hash for your package
yammy generate-hash ./yammy-hello

# Output: ABC123DEF456
```

### Step 5: Publish to GitHub

```bash
cd yammy-hello

# Add files
git add .
git commit -m "Initial release v1.0.0"

# Tag the release (optional but recommended)
git tag v1.0.0

# Create repo on GitHub, then:
git remote add origin https://github.com/your-username/yammy-hello
git push origin main
git push origin v1.0.0  # Push tag
```

### Step 6: Use in Your Project

```yaml
# your-project/yammy.yaml
name: my-app

require:
  yammy/hello: 1.0.0

packages:
  yammy/hello:
    src: "https://github.com/your-username/yammy-hello"
    hash: "ABC123DEF456"  # Hash from step 4
```

```bash
# Install
yammy install
```

### Step 7: Use the Package

```php
<?php
// your-app/index.php

require_once 'yammies/yammy/hello/Hello.php';

use Yammy\Hello\Hello;

echo Hello::greet('Oliver');
```

---

## Updating Your Package

### Release a New Version

```bash
# 1. Update your code
vim Hello.php

# 2. Commit changes
git add .
git commit -m "Fix bug in greeting function"

# 3. Tag the new version
git tag v1.0.1
git push origin main
git push origin v1.0.1

# 4. Generate NEW hash
cd ..
yammy generate-hash ./yammy-hello
# Output: XYZ789ABC012

# 5. Users update their yammy.yaml:
# require:
#   yammy/hello: 1.0.1  # Update version
# packages:
#   yammy/hello:
#     hash: "XYZ789ABC012"  # Update hash
```

---

## Package Security Checklist

Before publishing your package:

- [ ] `yammy.yaml` has correct name
- [ ] Version follows semantic versioning
- [ ] README.md with usage examples
- [ ] LICENSE file included
- [ ] No sensitive data (API keys, passwords)
- [ ] No malicious code
- [ ] Git tag matches yammy.yaml version
- [ ] Hash generated and shared with users

---

## Real-World Example: yammy/hello

See the complete example in `example-packages/yammy-hello/`:

```
yammy/example-packages/yammy-hello/
├── yammy.yaml          # Package manifest
├── Hello.php           # Main class with static methods
└── README.md           # Usage documentation
```

### Generate Hash for Example

```bash
cd yammy
yammy generate-hash ./example-packages/yammy-hello
```

### Use in Project

```yaml
# yammy.yaml
require:
  yammy/hello: 1.0.0

packages:
  yammy/hello:
    # For local testing (before GitHub)
    src: "./example-packages/yammy-hello"
    hash: "<generated-hash>"
    
    # For production (after GitHub)
    # src: "https://github.com/your-username/yammy-hello"
    # hash: "<generated-hash>"
```

## Package Naming Conventions

### Format: `vendor/package`

```yaml
✅ Good:
  yammy/hello
  kozma/logger
  acme/database-connector
  
❌ Bad:
  Hello               # Missing vendor
  yammy hello         # Space not allowed
  yammy\hello         # Wrong separator
  yammy.hello         # Wrong separator
```

### Best Practices

- **Lowercase only:** `yammy/hello` not `Yammy/Hello`
- **Hyphens for multi-word:** `yammy/http-client`
- **Vendor = your username/org:** `kozmaoliver/my-package`
- **Descriptive names:** `auth/oauth2` better than `auth/lib`

---

## Summary

### Required in Package

```
your-package/
├── yammy.yaml          # name (REQUIRED)
└── YourCode.php        # Your actual code
```

### Recommended Workflow

1. Write code
2. Create `yammy.yaml` with version
3. Create git tag matching version
4. Generate hash
5. Publish to GitHub
6. Share hash with users

### Version = Source of Truth

- `yammy.yaml` version is **required** and **validated**
- Git tags are **recommended** but not enforced (yet)
- Both should match for consistency

---

**Ready to create packages!**
