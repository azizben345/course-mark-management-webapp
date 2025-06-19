<template>
  <div class="dashboard">
    <header>
      <h1>Welcome, {{ roleLabel }}</h1>
      <button @click="logout">Logout</button>
    </header>

    <nav>
      <ul>
        <li v-for="item in menus" :key="item.name">
          <router-link :to="item.route">{{ item.name }}</router-link>
        </li>
      </ul>
    </nav>

    <main>
      <router-view /> <!-- Loads sub-pages based on route -->
    </main>
  </div>
</template>

<script>
export default {
  name: 'MyDashboard',
  data() {
    return {
      // Fetch the role from localStorage
      role: localStorage.getItem('role') || 'guest',
      menus: {
        lecturer: [
          { name: 'Manage Student', route: '/manage-student' },
          { name: 'Continuous Assessment', route: '/continuous-assessment' }
        ],
        student: [
          { name: 'Assessment', route: '/assessment' },
          { name: 'Compare Mark with Coursemates', route: '/compare-marks' },
          { name: 'Personal Class Rank', route: '/class-rank' },
          { name: 'Student Performance Expectation', route: '/performance' }
        ],
        advisor: [
          { name: 'Student-Advisor List', route: '/student-advisor-list' },
          { name: 'Meeting Records', route: '/meeting-records' }
        ],
        admin: [
          { name: 'Manage Users', route: '/manage-users' },
          { name: 'Assign Lecturers to Courses', route: '/assign-lecturers' }
        ]
      }
    };
  },
  computed: {
    // Use the role from localStorage to display the correct menu
    menusForRole() {
      return this.menus[this.role] || [];  // Get the menu items for the current role
    },
    roleLabel() {
      return {
        lecturer: 'Lecturer',
        student: 'Student',
        advisor: 'Academic Advisor',
        admin: 'Admin'
      }[this.role] || 'User';  // Default to 'User' if role is not recognized
    }
  },
  methods: {
    logout() {
      localStorage.removeItem('jwt');
      localStorage.removeItem('role');  // Remove the role when logging out
      this.$router.push('/');  // Redirect to the login page
    }
  }
};
</script>


<style scoped>
header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
nav ul {
  list-style: none;
  padding: 0;
}
nav li {
  margin-bottom: 8px;
}
</style>
