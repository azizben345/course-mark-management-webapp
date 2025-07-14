<template>
  <div style="padding: 24px">
    <h2>User Profile</h2>

    <!-- Show profile details -->
    <div v-if="localUser">
      <p><strong>Name:</strong> {{ localUser.name }}</p>
      <p><strong>Email:</strong> {{ localUser.email }}</p>
      <p><strong>Role:</strong> {{ formattedRole }}</p>
    </div>
    <div v-else>
      <p>Loading profile...</p>
    </div>
  </div>
</template>

<script>
export default {
  name: 'MyProfile',
  data() {
    return {
      localUser: null, // Default state as null until we load the data
    };
  },
  mounted() {
    // Fetch user data from localStorage when the profile page is mounted
    const userInfo = JSON.parse(localStorage.getItem('user_info'));
    if (userInfo) {
      this.localUser = userInfo;  // Set the user data to localUser
    }
  },
  computed: {
    formattedRole() {
      if (!this.localUser || !this.localUser.role) return 'Unknown Role';
      const roleMap = {
        admin: 'Admin',
        lecturer: 'Lecturer',
        student: 'Student',
        advisor: 'Advisor'
      };
      return roleMap[this.localUser.role] || this.localUser.role.charAt(0).toUpperCase() + this.localUser.role.slice(1);
    }
  }
}
</script>

<style scoped>
.avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: 2px solid #cbd5e0;
  cursor: pointer;
}

.sidebar {
  position: fixed;
  top: 0;
  right: 0;
  height: 100vh;
  width: 300px;
  background: #fff;
  box-shadow: -4px 0 12px rgba(0, 0, 0, 0.1);
  transform: translateX(100%);
  transition: transform 0.3s ease;
  z-index: 2000;
}

.sidebar.open {
  transform: translateX(0);
}

.header {
  display: flex;
  justify-content: flex-end;
  padding: 12px;
  border-bottom: 1px solid #e2e8f0;
}

.close {
  font-size: 22px;
  color: #718096;
  cursor: pointer;
}

.content {
  padding: 24px;
  text-align: center;
}

.avatar-large {
  width: 72px;
  height: 72px;
  border-radius: 50%;
  margin-bottom: 12px;
}

.name {
  font-weight: 600;
  font-size: 18px;
  margin-bottom: 4px;
}

.email {
  font-size: 14px;
  color: #4a5568;
}

.role {
  font-size: 13px;
  font-style: italic;
  color: #718096;
  margin-bottom: 24px;
}

.actions {
  border-top: 1px solid #e2e8f0;
  padding-top: 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

button {
  background: none;
  border: none;
  font-size: 14px;
  color: #2d3748;
  text-align: left;
  cursor: pointer;
}

.view-profile {
  color: #4a90e2;
}

.logout {
  color: #e53e3e;
}

.overlay-disabled {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background: rgba(0, 0, 0, 0.15);
  z-index: 1999;
  pointer-events: none;
}
</style>
