<template>
  <div>
    <div class="register-container">
      <h2>Register</h2>
      <form @submit.prevent="registerUser">
        <div>
          <label for="username">Username:</label>
          <input v-model="username" type="text" id="username" required />
        </div>

        <div>
          <label for="password">Password:</label>
          <input v-model="password" type="password" id="password" required />
        </div>

        <div>
          <label for="role">Role:</label>
          <!-- Using a select dropdown for roles is generally better than text input for fixed roles -->
          <select v-model="role" id="role" required>
            <option value="student">Student</option>
            <option value="lecturer">Lecturer</option>
            <option value="advisor">Advisor</option>
            <option value="admin">Admin</option>
          </select>
        </div>

        <!-- Student Details (conditionally displayed if role is 'student') -->
        <div v-if="role === 'student'">
          <h3>Student Details</h3>
          <div>
            <label for="matric_no">Matric No:</label>
            <input v-model="matric_no" type="text" id="matric_no" required />
          </div>

          <div>
            <label for="student_name">Student Name:</label>
            <input v-model="student_name" type="text" id="student_name" required />
          </div>

          <div>
            <label for="email">Email:</label>
            <input v-model="email" type="email" id="email" required />
          </div>

          <div>
            <label for="pin">PIN:</label>
            <input v-model="pin" type="password" id="pin" required />
          </div>
        </div>

        <button type="submit">Register</button>
      </form>
    </div>
    <p v-if="successMessage" class="success">{{ successMessage }}</p>
    <p v-else-if="errorMessage" class="error">{{ errorMessage }}</p>
    <p class="login-link">Already have an account? <router-link to="/">Login here</router-link></p>
  </div>
</template>

<script>
export default {
  name: 'MyRegister',
  data() {
    return {
      username: '',
      password: '',
      role: 'student', // Default role to student
      matric_no: '',
      student_name: '',
      email: '',
      pin: '',
      errorMessage: '',
      successMessage: ''
    };
  },
  methods: {
    async registerUser() {
      this.errorMessage = ''; // Clear previous errors
      this.successMessage = ''; // Clear previous success messages

      const registrationData = {
        username: this.username,
        password: this.password,
        role: this.role,
      };

      // Only add student-specific fields if the role is 'student'
      if (this.role === 'student') {
        registrationData.matric_no = this.matric_no;
        registrationData.student_name = this.student_name;
        registrationData.email = this.email;
        registrationData.pin = this.pin;

        // Basic client-side validation for student fields if student role is selected
        if (!this.matric_no || !this.student_name || !this.email || !this.pin) {
            this.errorMessage = 'All student details are required for student registration.';
            return;
        }
      }

      const API_ENDPOINT = 'http://localhost:8000/api/register'; // Your backend registration endpoint

      try {
        const response = await fetch(API_ENDPOINT, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(registrationData)
        });

        const data = await response.json();

        if (response.ok) {
          this.successMessage = `Registration successful! ${data.message || ''} Username: ${data.username}. You can now log in.`;
          console.log('Registration Success:', data);

          // Optional: Clear form fields after successful registration
          this.username = '';
          this.password = '';
          this.matric_no = '';
          this.student_name = '';
          this.email = '';
          this.pin = '';
          this.role = 'student'; // Reset role to default

          // Redirect to login page after a short delay
          setTimeout(() => {
            this.$router.push('/'); // Redirect to the login route
          }, 2000);

        } else {
          this.errorMessage = data.error || 'Registration failed.';
          console.error('Registration Error:', data);
        }
      } catch (error) {
        console.error('Network or Fetch Error during registration:', error);
        this.errorMessage = 'Network error. Could not connect to the server. Please try again.';
      }
    }
  }
};
</script>

<style scoped>
/* Copied from MyLogin.vue for consistent aesthetics */
.register-container { /* Changed .login-container to .register-container */
  max-width: 450px; /* Slightly wider for more fields */
  margin: auto;
  padding: 20px;
  border: 1px solid #ddd;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  background-color: #fff;
}

h2, h3 {
  text-align: center;
  color: #333;
  margin-bottom: 20px;
}

form div {
  margin-bottom: 15px;
}

label {
  display: block;
  margin-bottom: 5px;
  font-weight: bold;
  color: #555;
}

input[type="text"],
input[type="password"],
input[type="email"],
select {
  width: 100%;
  padding: 10px;
  border: 1px solid #ccc;
  border-radius: 4px;
  box-sizing: border-box; /* Ensures padding doesn't add to the width */
}

button {
  width: 100%;
  padding: 10px;
  background-color: #007bff;
  color: white;
  border: none;
  border-radius: 4px;
  font-size: 16px;
  cursor: pointer;
  transition: background-color 0.3s ease;
}

button:hover {
  background-color: #0056b3;
}

.error {
  color: red;
  margin-top: 10px;
  text-align: center;
}

.success {
  color: green;
  margin-top: 10px;
  text-align: center;
}

.login-link {
    margin-top: 20px;
    text-align: center;
    font-size: 14px;
}

.login-link a {
    color: #007bff;
    text-decoration: none;
}

.login-link a:hover {
    text-decoration: underline;
}
</style>
