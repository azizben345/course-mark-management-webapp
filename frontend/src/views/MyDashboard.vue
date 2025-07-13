<template>
  <div class="dashboard">
    <header>
      <h1>Welcome, {{ userDisplayName }} ({{ roleLabel }})</h1> <!-- Display user's name if available -->
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
      // This will store the parsed user_info object
      currentUserInfo: null, // Initialize as null, will be populated in created hook
      menuItems: {
        lecturer: [
          { name: 'Manage Students', route: '/lecturer/manage-students' },
          { name: 'Assessment Entry', route: '/lecturer/assessments' },
          { name: 'Final Exam Entry', route: '/lecturer/final-exam' },
          { name: 'Analytics', route: '/lecturer/analytics' }
        ],
        student: [
          { name: 'Assessment', route: '/student/assessment' },
          { name: 'Compare Marks', route: '/student/compare-marks' }, 
          { name: 'Class Rank', route: '/student/class-rank' },    
          { name: 'Performance Expectation', route: '/student/performance-expectation' },
          { name: 'Academic Progress', route: '/student/progress' }
        ],
        advisor: [
          { name: 'Student-Advisor List', route: '/advisor/student-advisor-list' },
          { name: 'Meeting Records', route: '/advisor/meeting-records' },
          { name: 'Manage Courses', route: '/advisor/manage-courses' },
        ],
        admin: [
          { name: 'Manage Users', route: '/admin/manage-users' },
        ]
      }
    };
  },

  computed: {
    // Read JWT token from localStorage
    jwtToken() {
      return localStorage.getItem('jwt_token');
    },
    // Get user role from currentUserInfo
    userRole() {
      return this.currentUserInfo ? this.currentUserInfo.role : 'guest';
    },
    // Display name (username or full_name from user_info)
    userDisplayName() {
      if (this.currentUserInfo && this.currentUserInfo.full_name) {
        return this.currentUserInfo.full_name;
      } else if (this.currentUserInfo && this.currentUserInfo.username) {
        return this.currentUserInfo.username;
      }
      return 'Guest'; // Fallback
    },
    // Dynamically select menu based on userRole
    menu() {
      return this.menuItems[this.userRole] || [];
    },
    // Label for the role
    roleLabel() {
      return {
        lecturer: 'Lecturer',
        student: 'Student',
        advisor: 'Academic Advisor',
        admin: 'Admin'
      }[this.userRole] || 'User';
    }
  },

  created() {
    // Attempt to load user info when the component is created
    this.loadUserInfo();
  },

  mounted() {
    // Perform authentication check after component is mounted and user info is loaded
    // Using this.userRole and this.jwtToken which are reactive computed properties
    if (!this.jwtToken || this.userRole === 'guest') {
      console.warn('Authentication token or user info missing in Dashboard. Redirecting to login.');
      this.logout(); // Use the logout method to clean up and redirect
    }
  },

  methods: {
    loadUserInfo() {
      const userInfoString = localStorage.getItem('user_info');
      if (userInfoString) {
        try {
          this.currentUserInfo = JSON.parse(userInfoString);
          console.log('Dashboard: Loaded user info:', this.currentUserInfo);
        } catch (e) {
          console.error("Error parsing user_info from localStorage during loadUserInfo:", e);
          this.currentUserInfo = null; // Reset if corrupted
          // Do not redirect here, let mounted hook handle the redirect via logout()
        }
      } else {
        this.currentUserInfo = null;
      }
    },
    logout() {
      // Remove both jwt_token and user_info from localStorage
      localStorage.removeItem('jwt_token');
      localStorage.removeItem('user_info');
      // Redirect to the login page (root path)
      this.$router.push('/');
    }
  }
};
</script>

<style scoped>
/* You can add more comprehensive styling here based on your overall design */
.dashboard {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
}

header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 15px 30px;
  background-color: #f8f9fa; /* Light background for header */
  border-bottom: 1px solid #e0e0e0;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
  color: #343a40;
}

header h1 {
  margin: 0;
  font-size: 1.8rem;
  color: #007bff;
}

header button {
  padding: 8px 15px;
  background-color: #dc3545; /* Red for logout button */
  color: white;
  border: none;
  border-radius: 5px;
  cursor: pointer;
  font-size: 0.9rem;
  transition: background-color 0.2s ease;
}

header button:hover {
  background-color: #c82333;
}

nav {
  background-color: #343a40; /* Dark background for navigation */
  padding: 15px 30px;
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

nav ul {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  gap: 25px; /* Spacing between menu items */
}

nav li a {
  text-decoration: none;
  color: #ffffff; /* White text for links */
  font-weight: bold;
  font-size: 1rem;
  padding: 5px 0;
  transition: color 0.2s ease, border-bottom 0.2s ease;
}

nav li a:hover {
  color: #007bff; /* Blue on hover */
  border-bottom: 2px solid #007bff;
}

main {
  flex-grow: 1; /* Allows main content to take up available space */
  padding: 30px;
  background-color: #f0f2f5; /* Light grey background for content area */
}
</style>
