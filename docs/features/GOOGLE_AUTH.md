# Google Authentication

The API supports social login using Google ID Tokens. This allows for a seamless "One-tap" or "Sign in with Google" experience on both Web and Mobile clients.

## Configuration

1. Obtain a Client ID from the [Google Cloud Console](https://console.cloud.google.com/).
2. Add it to your `.env` file:
   ```env
   GOOGLE_CLIENT_ID=your-google-client-id.apps.googleusercontent.com
   ```

## Authentication Flow

1. **Client-side**: The Web/Mobile app authenticates the user with Google and receives an **ID Token** (JWT).
2. **API Call**: The client sends the ID Token to the API:
   `POST /api/v1/auth/google`
   ```json
   {
     "id_token": "eyJhbGciOiJSUzI1NiIs..."
   }
   ```
3. **Verification**: The API verifies the token using the Google PHP Library, checking the signature, issuer, audience, and expiration.
4. **User Handling**:
   - **Existing User**: If the email matches an existing user, they are logged in. Their profile (name, avatar) is synchronized if it was empty.
   - **New User**: A new user is created with the `pending_approval` status.
   - **Social Sync**: If the user already exists but didn't have a linked Google account, the API links them automatically.

## User Lifecycle & Approval

By default, new users registered via Google are placed in a **Pending Approval** state to allow administrators to verify access.

- **Status**: `pending_approval`
- **Email**: An automated notification is sent to the user (via the Queue system) informing them that their account is awaiting review.
- **Activation**: An admin must change the status to `active` via the User Management API or Database.

## Security Notes

- The API **only** accepts ID Tokens, not Access Tokens. ID Tokens are designed for authentication and contain profile information signed by Google.
- Verification is performed on the server-side directly against Google's public keys.
