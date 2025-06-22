<template>
  <div>
    <div class="login-container">
      <h2>Login</h2>
      <form @submit.prevent="login"> <!-- Changed @submit.prevent="loginUser" to "login" -->
        <div>
          <label for="username">Username:</label>
          <input v-model="username" type="text" id="username" required />
        </div>

        <div>
          <label for="password">Password:</label>
          <input v-model="password" type="password" id="password" required />
        </div>

        <!-- The button should trigger the form's submit event -->
        <button type="submit">Login</button>

      </form>
    </div>
    <p v-if="successMessage" class="success">{{ successMessage }}</p>
    <p v-else-if="errorMessage" class="error">{{ errorMessage }}</p>
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
        this.errorMessage = ''; // Clear previous errors
        this.successMessage = ''; // Clear previous success messages

        try {
            // Send POST request to /api/login for authentication
            // CORRECTED URL to match backend /api/login endpoint
            const res = await fetch('http://localhost:8000/api/login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    username: this.username,
                    password: this.password
                })
            });

            const data = await res.json();

            if (res.ok && data.token) {
                // Login successful
                this.successMessage = `Login successful! Welcome, ${data.user.username}.`;

                // Store JWT token in localStorage under the 'jwt_token' key
                localStorage.setItem('jwt_token', data.token);

                // Store user info (id, username, role) in localStorage under 'user_info' key
                localStorage.setItem('user_info', JSON.stringify(data.user)); // Store the user object

                // Redirect to dashboard based on role or a default dashboard
                // CORRECTED REDIRECTION to use window.location.href for static HTML files
                if (data.user.role === 'student') {
                    setTimeout(() => {
                        this.$router.push('/dashboard')
                    }, 500); // Small delay for message visibility
                } else {
                    // Handle other roles or a general dashboard
                    // For example, redirect to a generic dashboard.html or specific lecturer_dashboard.html
                    // window.location.href = 'dashboard.html';
                    this.successMessage = `Login successful for ${data.user.role}: ${data.user.username}. Redirecting...`;
                    setTimeout(() => {
                        this.$router.push('/dashboard'); 
                    }, 500);
                }

            } else {
                // Login failed (e.g., 401 Unauthorized, 400 Bad Request)
                this.errorMessage = data.error || 'Login failed. Please check your credentials.';
            }
        } catch (err) {
            console.error('Login error:', err);
            this.errorMessage = 'Network error or unexpected issue. Please try again.';
        }
    }
  }
};
</script>

<style scoped>
.login-container {
  max-width: 400px;
  margin: auto;
  padding: 20px;
}
.error {
  color: red;
  margin-top: 10px;
}
.success {
  color: green;
  margin-top: 10px;
}
</style>
