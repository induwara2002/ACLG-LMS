# Backup and Data Protection

- Back up the production database at least daily, and more frequently when the library's transaction volume or recovery objectives require it.
- Retain daily backups for at least 30 days; keep longer-term monthly recovery points according to the organization's retention policy.
- Store encrypted backups off the application server in access-controlled storage, and restrict decryption keys separately from the backup files.
- Test restores regularly in an isolated environment and verify application integrity before relying on a backup for recovery.
- Never commit database dumps or backup files containing member, payment, borrowing, or staff data.
- Never commit `.env`, production credentials, application keys, or database passwords. Keep deployment secrets in an approved secret manager.
- Protect local and production storage, application logs, and exported reports with least-privilege filesystem and account access.

Member NIC, phone, address, and email remain searchable by existing staff search and filtering flows. Field encryption is not enabled because ordinary `LIKE` and equality searches would stop working; protect the database and backups, restrict access by role, and review searchable-encryption options before changing that behavior.