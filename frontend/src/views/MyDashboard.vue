<template>
  <div class="dashboard">
    <header>
      <h1>Welcome, {{ roleLabel }}</h1>
      <button @click="logout">Logout</button>
    </header>

    <nav>
      <ul>
        <li v-for="item in menu" :key="item.name">
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
      role: localStorage.getItem('role') || 'guest',
      menuItems: {
        lecturer: [
          { name: 'Manage Students', route: '/lecturer/manage-students' },
          { name: 'Assessment Entry', route: '/lecturer/assessments' },
          { name: 'Final Exam Entry', route: '/lecturer/final-exam' },
          { name: 'Analytics', route: '/lecturer/analytics' }
        ],
        student: [
          { name: 'Progress', route: '/student/progress' },
          { name: 'Compare Marks', route: '/student/comparison' },
          { name: 'Ranking', route: '/student/ranking' }
        ],
        advisor: [
          { name: 'Advisee List', route: '/advisor/advisees' },
          { name: 'At Risk Students', route: '/advisor/risk' }
        ]
      }
    };
  },
  computed: {
    menu() {
      return this.menuItems[this.role] || [];
    },
    roleLabel() {
      return {
        lecturer: 'Lecturer',
        student: 'Student',
        advisor: 'Academic Advisor',
        admin: 'Admin'
      }[this.role] || 'User';
    }
  },
  methods: {
    logout() {
      localStorage.removeItem('jwt');
      localStorage.removeItem('role');
      this.$router.push('/');
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
