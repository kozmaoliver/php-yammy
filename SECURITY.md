# Yammy Security Architecture

## 🔒 Security Features

### 1. **Quarantine System**
All packages are downloaded to a `.quarantine/` directory first, where they undergo security checks before being moved to production.

**Benefits:**
- No untrusted code can execute during download
- Failed packages remain isolated for forensic analysis
- Clean separation between trusted and untrusted code

**Implementation:**
```
yammies/
├── .quarantine/          # Temporary, untrusted packages
│   └── my-pkg_timestamp/ # Failed package kept here
└── my-pkg/               # Production, verified package
```

### 2. **Cryptographic Hash Verification**
Every package must have a hash specified in `yammy.yaml`. The hash is computed using xxHash (fast, collision-resistant) over all relevant files.

**What's hashed:**
- `.php`, `.phtml`, `.html`, `.js`, `.yaml` files
- Recursive directory scanning
- Excludes: `.git/`, `yammy.lock`, symlinks

**Verification flow:**
1. Download package to quarantine
2. Compute actual hash
3. Compare with expected hash from yammy.yaml
4. Reject if mismatch (package stays in quarantine)
5. Move to production only if verified

### 3. **Input Validation**
All user inputs and external data are validated:

**Package names:**
- Must match: `^[a-zA-Z0-9_\-\/]+$`
- Prevents directory traversal attacks

**Versions:**
- Must follow semantic versioning: `\d+\.\d+\.\d+(-[a-zA-Z0-9]+)?`
- Examples: `1.0.0`, `2.1.3-beta`

**Git URLs:**
- Must start with `https://`, `http://`, or `git@`
- All shell arguments are escaped via `escapeshellarg()`

**Manifest validation:**
- Required fields: `name`
- Package name must match manifest

### 4. **Security Logging**
All security-relevant events are logged to `yammy-security.log`:

**Logged events:**
- `INSTALL_SUCCESS` - Package installed successfully
- `HASH_MISMATCH` - Integrity verification failed
- `INTEGRITY_CHECK_FAILED` - Post-install check failed

**Log format:**
```
[2024-02-24 16:00:00] HASH_MISMATCH - yammy/hello@1.1.0: Expected: ABC123, Got: DEF456
```

### 5. **Lock File with Hashes**
`yammy.lock` includes:
- Exact versions installed
- Hash of each installed package
- Generation timestamp
- Yammy version used

**Purpose:**
- Reproducible builds
- Detect tampering after installation
- Audit trail

### 6. **Manifest Identity Verification**
Before accepting a package, Yammy verifies:
1. Package name in manifest matches expected name
2. Prevents package spoofing

### 7. **Safe Defaults**
- Hash verification is **required** (warns if missing)
- Interactive confirmation if no hash provided
- Set `YAMMY_AUTO_APPROVE=1` to skip (CI/CD only)
- `.git` directories removed after clone (prevents git operations)

---

## 🚨 Attack Vectors & Mitigations

### Attack: Compromised Package Source
**Scenario:** Attacker gains control of GitHub repo and pushes malicious code

**Mitigation:**
- Hash verification detects changes
- Package fails installation
- Stays in quarantine for analysis

### Attack: Man-in-the-Middle
**Scenario:** Network attacker modifies package during download

**Mitigation:**
- HTTPS enforced for Git URLs
- Hash verification detects tampering
- Even if MitM successful, hash won't match

### Attack: Directory Traversal
**Scenario:** Malicious package name like `../../etc/passwd`

**Mitigation:**
- Package name validation with regex
- Rejects illegal characters
- Cannot escape package directory

### Attack: Command Injection
**Scenario:** Malicious Git URL with shell metacharacters

**Mitigation:**
- Git URL format validation
- `escapeshellarg()` on all shell parameters
- Only whitelisted characters in package names

### Attack: Zip Slip / Path Traversal in Archives
**Scenario:** Package contains files with `../` in paths

**Mitigation:**
- Using Git clone (not archives)
- No extraction of user-provided archives
- All paths validated

### Attack: Post-Install Tampering
**Scenario:** Attacker modifies installed package files

**Mitigation:**
- `yammy check-integrity` command
- Compares current hash with lock file
- Detects any modifications

### Attack: Dependency Confusion
**Scenario:** Attacker creates package with same name as internal package

**Mitigation:**
- Explicit source URLs in `packages:` section
- No automatic public registry lookups
- User controls where packages come from

---

## Best Practices

### For Package Authors

1. **Always provide hashes:**
   ```bash
   yammy generate-hash ./your-package
   ```

2. **Sign your releases** (future feature):
   Use GPG signatures for additional verification

3. **Minimal dependencies:**
   Fewer dependencies = smaller attack surface

4. **Security disclosure:**
   Provide `SECURITY.md` in your package

### For Package Users

1. **Always specify hashes** in `yammy.yaml`:
   ```yaml
   packages:
     vendor/package:
       src: "https://github.com/vendor/package"
       hash: "ABC123DEF456"
   ```

2. **Regular integrity checks:**
   ```bash
   yammy check-integrity
   ```

3. **Review quarantined packages:**
   When installation fails, inspect `.quarantine/` before deleting

4. **Pin exact versions:**
   Use `vendor/package: 1.2.3` not `vendor/package: ^1.2`

5. **Audit security logs:**
   ```bash
   cat yammy-security.log
   ```

6. **Keep Yammy updated:**
   Security fixes are released regularly

---

## 📋 Security Checklist

Before deploying to production:

- [ ] All packages have hashes specified
- [ ] `yammy check-integrity` passes
- [ ] No packages in `.quarantine/`
- [ ] Review `yammy-security.log` for issues
- [ ] `yammy.lock` committed to version control
- [ ] No `YAMMY_AUTO_APPROVE` in production
- [ ] Regular security audits scheduled

---

## 🔮 Future Security Features

### Planned for v2.0:

1. **GPG Signature Verification**
   - Package authors sign releases with GPG
   - Yammy verifies signatures before installation
   - Chain of trust

2. **Vulnerability Database**
   - Check packages against known CVEs
   - `yammy audit` command
   - Integration with security advisories

3. **Sandboxed Execution**
   - Run package install scripts in containers
   - Restricted filesystem access
   - Network isolation

4. **Permission System**
   - Packages declare required permissions
   - User approves before installation
   - Runtime enforcement

5. **Supply Chain Verification**
   - SLSA compliance
   - Build provenance
   - Reproducible builds

6. **Automatic Hash Updates**
   - CI/CD integration
   - Auto-generate and commit hashes
   - Pull request automation

7. **Multi-Hash Support**
   - SHA-256 + xxHash
   - Algorithm agility
   - Stronger cryptographic guarantees

8. **Repository Mirroring**
   - Self-hosted package mirrors
   - Reduced external dependencies
   - Air-gapped environments

---

## 📞 Security Contact

To report security vulnerabilities, DO NOT open public issues.

**Report via:**
- Email: [your-security-email]
- Security advisory: GitHub Security tab
- GPG Key: [your-gpg-key-id]

**Response time:** 48 hours for critical issues

---

## 📜 License & Credits

Yammy Security Model inspired by:
- Go modules (cryptographic checksums)
- Nix (content-addressed storage)
- Composer (lock files)
- npm audit (vulnerability scanning)

Built with security-first mindset 🔒
