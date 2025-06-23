<template>
  <div>
    <div class="login-container">
      <h2>Login</h2>

      <form @submit.prevent="login">
        <div class="form-row">
          <label for="username">Username:</label>
          <input v-model="username" type="text" id="username" required />
        </div>

        <div class="form-row">
          <label for="password">Password:</label>
          <input v-model="password" type="password" id="password" required />
        </div>

        <button type="submit">Login</button>

        <p class="register-link">
          Don't have an account?
          <router-link to="/register">Register here</router-link>
        </p>
      </form>
    </div>

    <!-- Feedback messages will appear below the container -->
    <p v-if="successMessage" class="success">{{ successMessage }}</p>
    <p v-if="errorMessage" class="error">{{ errorMessage }}</p>
  </div>
</template>

<script>
export default {
  name: 'MyLogin',
  data() {
    return {
      username: '',
      password: '',
      errorMessage: '',
      successMessage: ''
    };
  },
  methods: {
    async login() {
      // Clear previous messages at the start of the login attempt
      this.errorMessage = '';
      this.successMessage = '';
      console.group('Login Process'); // Start a console group for better readability
      console.log('Login attempt started for user:', this.username);

      try {
        const res = await fetch('http://localhost:8000/api/login', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            username: this.username,
            password: this.password
          })
        });

        const data = await res.json();
        console.log('Login API raw response data:', data);

        if (res.ok && data.token) {
          console.log('Login API response successful. Token received.');
          this.successMessage = `Login successful! Welcome, ${data.user.username}. Redirecting...`;

          // --- CRITICAL FIX 1: Store JWT token using the consistent key 'jwt_token' ---
          localStorage.setItem('jwt_token', data.token);
          console.log('localStorage: jwt_token set to:', localStorage.getItem('jwt_token'));

          // --- CRITICAL FIX 2: Store user info object using the consistent key 'user_info' ---
          // The /api/login response already provides the full user object (id, username, role)
          // Store this as a JSON string under 'user_info'. This replaces the separate /api/me/role call.
          localStorage.setItem('user_info', JSON.stringify(data.user));
          console.log('localStorage: user_info set to:', localStorage.getItem('user_info'));

          console.log('Attempting redirection to /dashboard...');

          if (this.$router) {
              console.log('Vue Router instance found. Calling this.$router.push(/dashboard).');
              // Using .then().catch() to log router push success/failure
              this.$router.push('/dashboard').then(() => {
                console.log('Router push to /dashboard completed successfully (promise resolved).');
              }).catch(err => {
                console.error('Router push to /dashboard failed (promise rejected):', err);
                this.errorMessage = 'Navigation error after login.';
              });
          } else {
              console.warn('Vue Router instance not found. Falling back to window.location.href. Ensure router is correctly installed/configured in main.js.');
              window.location.href = 'dashboard.html'; // Fallback
          }

        } else {
          // Login failed (e.g., 401 Unauthorized, 400 Bad Request)
          this.errorMessage = data.error || 'Login failed. Please check your credentials.';
          console.error('Login failed with API error:', this.errorMessage);
        }
      } catch (err) {
        console.error('Login network/fetch error or uncaught exception:', err);
        this.errorMessage = 'Network error. Could not connect to the server. Please try again.';
      } finally {
        console.groupEnd(); // End the console group
      }
    }
  }
};
</script>

<style scoped>
/* Styles copied and adapted from MyRegister.vue for consistency */
.login-container {
  max-width: 400px;
  margin: 20px auto;
  padding: 30px;
  border: 1px solid #ddd;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  background-color: #fff;
}

h2 {
  text-align: center;
  color: #333;
  margin-bottom: 25px;
  font-size: 24px;
}

.form-row {
  display: flex;
  align-items: center;
  margin-bottom: 15px;
}

.form-row label {
  width: 120px;
  margin-right: 15px;
  font-weight: bold;
  text-align: right;
  color: #555;
}

.form-row input {
  flex: 1;
  padding: 10px 12px;
  border: 1px solid #ccc;
  border-radius: 5px;
  box-sizing: border-box;
  font-size: 16px;
}

button {
  width: 100%;
  padding: 12px;
  background-color: #007bff;
  color: white;
  border: none;
  border-radius: 5px;
  font-size: 18px;
  cursor: pointer;
  transition: background-color 0.3s ease, transform 0.2s ease;
  margin-top: 20px;
}

button:hover {
  background-color: #0056b3;
  transform: translateY(-2px);
}

.error {
  color: #dc3545;
  margin-top: 15px;
  text-align: center;
  font-weight: bold;
}

.success {
  color: #28a745;
  margin-top: 15px;
  text-align: center;
  font-weight: bold;
}

.register-link {
  margin-top: 25px;
  text-align: center;
  font-size: 15px;
  color: #666;
}

.register-link a {
  color: #007bff;
  text-decoration: none;
  font-weight: bold;
}

.register-link a:hover {
  text-decoration: underline;
}
</style>
