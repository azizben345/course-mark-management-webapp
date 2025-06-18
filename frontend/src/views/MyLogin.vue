<template>
  <div class="login-container">
    <h2>Login</h2>
    <form @submit.prevent="loginUser">
      <div>
        <label for="username">Username:</label>
        <input v-model="username" type="text" id="username" required />
      </div>

      <div>
        <label for="password">Password:</label>
        <input v-model="password" type="password" id="password" required />
      </div>

      <button @click="login">Login</button>

    </form>
  </div>
  <p v-if="errorMessage" class="error">{{ errorMessage }}</p>
    <p v-if="successMessage" style="color: green;">{{ successMessage }}</p>
    <p v-if="errorMessage" style="color: red;">{{ errorMessage }}</p>
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
      try {
        const res = await fetch('http://localhost:8000/login', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            username: this.username,
            password: this.password
          })
        });

        const data = await res.json();

        if (res.ok && data.token) {
          localStorage.setItem('jwt', data.token);
          this.successMessage = 'Login successful!';
          console.log('JWT:', data.token);
          console.log('Router:', this.$router);

          // Delay navigation to show confirmation
          setTimeout(() => {
            this.$router.push('/lecturer/dashboard');
          }, 1000);

        } else {
          this.errorMessage = data.error || 'Login failed';
        }

      } catch (err) {
        console.error('Login error:', err);
        this.errorMessage = 'Login error. Please try again.';
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
</style>
