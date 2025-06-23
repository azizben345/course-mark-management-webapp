<template>
  <div class="dashboard">
    <header>
      <h1>Welcome, {{ usernameDisplay }} ({{ roleLabel }})</h1> <!-- Display username and role -->
      <button @click="logout">Logout</button>
    </header>

    <div class="user-details" v-if="role === 'student' && studentProfileLoaded">
      <h2>Student Profile</h2>
      <p>Student ID: {{ studentId || 'N/A' }}</p>
      <p>Matric No: {{ matricNo || 'N/A' }}</p>
      <!-- Add more student-specific data here as you fetch it -->
    </div>
    <div v-else-if="role === 'student' && !studentProfileLoaded">
        <p>Loading student profile...</p>
    </div>
    <div v-else-if="role === 'student' && studentProfileError">
        <p class="error">Error loading student profile: {{ studentProfileError }}</p>
    </div>


    <nav>
      <h3>Navigation</h3>
      <ul>
        <li v-for="item in menusForRole" :key="item.name">
          <router-link :to="item.route">{{ item.name }}</router-link>
        </li>
      </ul>
    </nav>
  </div>
</template>

<script>
export default {
  name: 'MyDashboard',
  data() {
    return {
      // Initialize with default values, actual values will be loaded from localStorage/API
      role: 'guest',
      username: '',
      userId: null,
      studentId: null,
      matricNo: '',
      studentProfileLoaded: false,
      studentProfileError: '',
      menus: {
        lecturer: [
          { name: 'Manage Student', route: '/lecturer/manage-students' },
          { name: 'Continuous Assessment', route: '/lecturer/assessments' }
        ],
        student: [
          { name: 'Assessment', route: '/student/assessment' },
          { name: 'Student Ranking', route: '/student/studentranking' },
          { name: 'Student Performance Expectation', route: '/student/performance-expectation' }
        ],
        advisor: [
          { name: 'Student-Advisor List', route: '/advisor/student-advisor-list' },
          { name: 'Meeting Records', route: '/advisor/meeting-records' }
        ],
        admin: [
          { name: 'Manage Users', route: '/admin/manage-users' },
        ]
      }
    };
  },
  computed: {
    menusForRole() {
      return this.menus[this.role] || [];
    },
    roleLabel() {
      return {
        lecturer: 'Lecturer',
        student: 'Student',
        advisor: 'Academic Advisor',
        admin: 'Admin'
      }[this.role] || 'User';
    },
    usernameDisplay() {
      // Display username if available, fallback to role label
      return this.username || this.roleLabel;
    }
  },
  methods: {
    async fetchUserProfileAndData() {
        const token = localStorage.getItem('jwt_token');
        const userInfoString = localStorage.getItem('user_info');

        if (!token || !userInfoString) {
            console.warn("Authentication token or user info missing. Redirecting to login.");
            this.logout(); // Use logout to clear and redirect
            return;
        }

        let userInfo;
        try {
            userInfo = JSON.parse(userInfoString);
            if (!userInfo || !userInfo.id || !userInfo.username || !userInfo.role) {
                console.error("Malformed user info. Redirecting to login.");
                this.logout();
                return;
            }
            // Set basic user info from localStorage
            this.userId = userInfo.id;
            this.username = userInfo.username;
            this.role = userInfo.role;

            // If the user is a student, fetch their specific student profile data
            if (this.role === 'student') {
                await this.fetchStudentProfileData(token, this.userId);
            }
        } catch (e) {
            console.error("Error parsing user info from localStorage:", e);
            this.logout();
            return;
        }
    },

    async fetchStudentProfileData(token, userId) {
        this.studentProfileLoaded = false;
        this.studentProfileError = '';
        const API_ENDPOINT = `http://localhost:8000/api/students/${userId}`;

        try {
            const response = await fetch(API_ENDPOINT, {
                method: 'GET',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                }
            });

            if (response.ok) {
                const studentData = await response.json();
                this.studentId = studentData.student_id;
                this.matricNo = studentData.matric_no;
                this.studentProfileLoaded = true;
            } else if (response.status === 401) {
                console.error("Unauthorized: Invalid or expired token. Redirecting to login.");
                this.studentProfileError = 'Session expired. Please log in again.';
                this.logout();
            } else if (response.status === 404) {
                console.error("Student data not found for this user.");
                this.studentProfileError = 'Student profile not found.';
            } else {
                const errorResult = await response.json();
                console.error(`API Error: ${response.status} - ${errorResult.error || 'Unknown error'}`);
                this.studentProfileError = `Error: ${errorResult.error || 'Failed to fetch profile'}`;
            }
        } catch (error) {
            console.error("Network or Fetch Error:", error);
            this.studentProfileError = 'Network error. Could not connect to API.';
        }
    },

    logout() {
      // Clear all authentication-related data from localStorage
      localStorage.removeItem('jwt_token');
      localStorage.removeItem('user_info');

      // Redirect to the login page using Vue Router
      this.$router.push('/');
    }
  },
  mounted() {
    this.fetchUserProfileAndData();
  }
};
</script>

<style scoped>
header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 20px;
  background-color: #f0f0f0;
  border-bottom: 1px solid #ddd;
}
nav ul {
  list-style: none;
  padding: 0;
  margin-top: 20px;
}
nav li {
  margin-bottom: 8px;
}
nav a {
  text-decoration: none;
  color: #007bff;
  font-weight: bold;
}
nav a:hover {
  text-decoration: underline;
}
.user-details {
    margin-top: 20px;
    padding: 15px;
    border: 1px solid #eee;
    background-color: #f9f9f9;
    border-radius: 8px;
}
.error {
    color: red;
}
</style>
