# Auth system

**Intent**: Build a complete customer password authentication system with email, password, OTP flows, and two-factor authentication.

**Status**: in-progress

**Done when**: Users can register, log in, log out, reset their password, verify their email, enable 2FA, and delete their account. All flows work end-to-end with Sanctum tokens, OTP emails are sent, and tests pass.

**Spec**: [0001](../specs/0001-password-auth-with-email.md)

## Sub-tasks

- [x] Design it (spec)
- [x] Build it: /develop auth
  - [x] Data layer: migration, model, repository, enums (satisfies AC-1)
  - [x] Core auth: register, login, logout, OTP service, Mailables (satisfies AC-1, AC-2, AC-3, AC-16)
  - [x] Password reset + email verification flows (satisfies AC-4, AC-5, AC-6, AC-8, AC-9, AC-10, AC-15)
  - [x] Update password + email flows (satisfies AC-7, AC-10)
  - [x] Two-factor authentication flow (satisfies AC-11, AC-12)
  - [x] GDPR: account deletion, data export (satisfies AC-13, AC-14)
- [ ] Verify it: /check verify auth
- [ ] Test it: /test auth
