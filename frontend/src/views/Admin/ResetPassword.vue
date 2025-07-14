<template>
  <div class="reset-password-container">
    <h2>Reset Password</h2>
    <form @submit.prevent="submitResetPassword">
      <div>
        <label for="currentPassword">Current Password:</label>
        <input type="password" v-model="currentPassword" id="currentPassword" required />
      </div>
      <div>
        <label for="newPassword">New Password:</label>
        <input type="password" v-model="newPassword" id="newPassword" required />
      </div>
      <div>
        <label for="confirmPassword">Confirm New Password:</label>
        <input type="password" v-model="confirmPassword" id="confirmPassword" required />
      </div>
      <button type="submit">Reset Password</button>
    </form>
    <p v-if="errorMessage" class="error">{{ errorMessage }}</p>
    <p v-if="successMessage" class="success">{{ successMessage }}</p>
  </div>
</template>

<script>
export default {
  data() {
    return {
      currentPassword: '',
      newPassword: '',
      confirmPassword: '',
      errorMessage: '',
      successMessage: ''
    };
  },
  methods: {
    submitResetPassword() {
      if (this.newPassword !== this.confirmPassword) {
        this.errorMessage = "New password and confirm password don't match.";
        return;
      }

      // Get the user info from localStorage (user_id should be part of user_info)
      const userInfo = JSON.parse(localStorage.getItem('user_info'));
      const userId = userInfo.id;

      // Make API call to reset the password
      fetch('http://localhost:8000/api/reset-password', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          userId: userId,  // Make sure this is `userId`
          oldPassword: this.currentPassword,  // Match with backend key 'oldPassword'
          newPassword: this.newPassword       // Match with backend key 'newPassword'
        })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          this.successMessage = "Password successfully reset!";
        } else {
          this.errorMessage = data.message || "An error occurred.";
        }
      })
      .catch(error => {
        this.errorMessage = 'Error resetting password. Please try again later.';
        console.error('Error:', error);
      });
    }
  }
}
</script>

<style scoped>
.reset-password-container {
  padding: 20px;
}

form {
  display: flex;
  flex-direction: column;
}

input {
  margin: 10px 0;
  padding: 8px;
}

button {
  padding: 10px;
  background-color: #4CAF50;
  color: white;
  border: none;
  cursor: pointer;
}

button:hover {
  background-color: #45a049;
}

.error {
  color: red;
}

.success {
  color: green;
}
</style>
