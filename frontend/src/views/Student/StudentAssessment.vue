<template>
  <div class="student-assessment-container">
    <h2>My Courses & Assessments</h2>

    <!-- Display List of Enrollments/Courses -->
    <div v-if="loadingCourses" class="loading-message">Loading your courses...</div>
    <div v-else-if="courseError" class="error-message">Error: {{ courseError }}</div>
    <div v-else-if="enrollments.length === 0" class="no-data-message">You are not currently enrolled in any courses.</div>
    <div v-else class="course-list">
      <h3>Enrolled Courses</h3>
      <ul>
        <li v-for="enrollment in enrollments" :key="enrollment.enrollment_id"
            @click="selectEnrollment(enrollment)"
            :class="{ 'selected': selectedEnrollment && selectedEnrollment.enrollment_id === enrollment.enrollment_id }">
          <strong>{{ enrollment.course_code }} - {{ enrollment.course_name }}</strong> ({{ enrollment.academic_year }})
          <br>
          Lecturer: {{ enrollment.lecturer_name }}
          <br>
          Overall Total: {{ enrollment.final_total || 'N/A' }} / 100
        </li>
      </ul>
    </div>

    <!-- Display Selected Course's Assessment Details -->
    <div v-if="selectedEnrollment" class="assessment-details">
      <h3>Assessment Details for {{ selectedEnrollment.course_code }} - {{ selectedEnrollment.course_name }}</h3>

      <div v-if="loadingComponents" class="loading-message">Loading assessment components...</div>
      <div v-else-if="componentError" class="error-message">Error: {{ componentError }}</div>
      <div v-else-if="componentsAndMarks.length === 0" class="no-data-message">No assessment components found for this course.</div>
      <div v-else>
        <table>
          <thead>
            <tr>
              <th>Component Name</th>
              <th>Marks Obtained</th>
              <th>Max Mark</th>
              <th>Percentage (%)</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="component in componentsAndMarks" :key="component.component_id">
              <td>{{ component.component_name }}</td>
              <td>{{ component.mark_obtained !== null ? component.mark_obtained : 'N/A' }}</td>
              <td>{{ component.max_mark }}</td>
              <td>
                <span v-if="component.mark_obtained !== null">
                  {{ calculatePercentage(component.mark_obtained, component.max_mark).toFixed(2) }}%
                </span>
                <span v-else>N/A</span>
              </td>
            </tr>
          </tbody>
        </table>

        <div class="total-marks">
          <h4>Calculated Total (Based on available component marks): {{ calculatedTotalPercentage.toFixed(2) }}%</h4>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
//import NavBar from '../../components/NavBar.vue';

export default {
  name: 'StudentAssessment',
  // components: {
  //   NavBar, // Register the AppHeaderNav component so it can be used in the template
  // },
  data() {
    return {
      userId: null,
      token: null,
      enrollments: [],
      selectedEnrollment: null,
      componentsAndMarks: [],
      loadingCourses: true,
      loadingComponents: false,
      courseError: '',
      componentError: ''
    };
  },
  computed: {
    calculatedTotalPercentage() {
      // This calculates the total based on component marks fetched.
      // IMPORTANT: This simple calculation assumes equal weighting or max_mark represents contribution.
      // For true weighted totals, you need a 'weight' column in assessment_components.
      if (!this.componentsAndMarks || this.componentsAndMarks.length === 0) {
        return 0;
      }

      let totalObtained = 0;
      let totalMax = 0;

      this.componentsAndMarks.forEach(component => {
        if (component.mark_obtained !== null) {
          totalObtained += parseFloat(component.mark_obtained);
          totalMax += parseFloat(component.max_mark);
        }
      });

      if (totalMax === 0) {
        return 0;
      }
      return (totalObtained / totalMax) * 100;
    }
  },
  methods: {
    redirectToLogin() {
      localStorage.removeItem('jwt_token');
      localStorage.removeItem('user_info');
      if (this.$router) {
        this.$router.push('/');
      } else {
        window.location.href = 'login.html'; // Fallback
      }
    },

    async fetchEnrollments() {
      this.loadingCourses = true;
      this.courseError = '';
      this.enrollments = [];
      const userInfoString = localStorage.getItem('user_info');

      if (!this.token || !userInfoString) {
        this.courseError = "Authentication data missing. Please log in again.";
        this.redirectToLogin();
        return;
      }

      let userInfo;
      try {
        userInfo = JSON.parse(userInfoString);
        this.userId = userInfo.id;
      } catch (e) {
        this.courseError = "Error parsing user info. Please log in again.";
        this.redirectToLogin();
        return;
      }

      if (!this.userId) {
        this.courseError = "User ID not found. Cannot fetch enrollments.";
        this.redirectToLogin();
        return;
      }

      const API_ENDPOINT = `http://localhost:8000/api/students/${this.userId}/enrollments`;

      try {
        const response = await fetch(API_ENDPOINT, {
          method: 'GET',
          headers: {
            'Authorization': `Bearer ${this.token}`,
            'Content-Type': 'application/json'
          }
        });

        if (response.ok) {
          this.enrollments = await response.json();
          // Automatically select the first enrollment if available
          if (this.enrollments.length > 0) {
            this.selectEnrollment(this.enrollments[0]);
          }
        } else if (response.status === 401 || response.status === 403) {
          this.courseError = 'Session expired or unauthorized. Please log in.';
          this.redirectToLogin();
        } else {
          const errorResult = await response.json();
          this.courseError = `Failed to fetch courses: ${errorResult.error || 'Unknown error'}`;
        }
      } catch (error) {
        console.error("Network or Fetch Error for enrollments:", error);
        this.courseError = 'Network error. Could not load courses.';
      } finally {
        this.loadingCourses = false;
      }
    },

    async selectEnrollment(enrollment) {
      this.selectedEnrollment = enrollment;
      await this.fetchComponentsAndMarks(enrollment.enrollment_id);
    },

    async fetchComponentsAndMarks(enrollmentId) {
      this.loadingComponents = true;
      this.componentError = '';
      this.componentsAndMarks = [];

      if (!this.token) {
        this.componentError = "Authentication token missing. Please log in again.";
        this.redirectToLogin();
        return;
      }

      const API_ENDPOINT = `http://localhost:8000/api/enrollments/${enrollmentId}/components-and-marks`;

      try {
        const response = await fetch(API_ENDPOINT, {
          method: 'GET',
          headers: {
            'Authorization': `Bearer ${this.token}`,
            'Content-Type': 'application/json'
          }
        });

        if (response.ok) {
          this.componentsAndMarks = await response.json();
        } else if (response.status === 401 || response.status === 403) {
          this.componentError = 'Session expired or unauthorized to view these marks.';
          this.redirectToLogin();
        } else if (response.status === 404) {
             this.componentError = 'No assessment components found for this enrollment or enrollment not found.';
        }
        else {
          const errorResult = await response.json();
          this.componentError = `Failed to fetch components: ${errorResult.error || 'Unknown error'}`;
        }
      } catch (error) {
        console.error("Network or Fetch Error for components:", error);
        this.componentError = 'Network error. Could not load assessment components.';
      } finally {
        this.loadingComponents = false;
      }
    },

    calculatePercentage(obtained, max) {
      if (max === 0) return 0;
      return (parseFloat(obtained) / parseFloat(max)) * 100;
    }
  },
  created() {
    // Get token when component is created
    this.token = localStorage.getItem('jwt_token');
    if (!this.token) {
      this.redirectToLogin();
      return;
    }
    this.fetchEnrollments();
  }
};
</script>

<style scoped>
.student-assessment-container {
  max-width: 900px;
  margin: 20px auto;
  padding: 20px;
  background-color: #fff;
  border-radius: 8px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

h2, h3 {
  color: #333;
  margin-bottom: 15px;
  text-align: center;
}

.loading-message, .error-message, .no-data-message {
  padding: 15px;
  margin-bottom: 15px;
  border-radius: 5px;
  text-align: center;
}

.loading-message {
  background-color: #e0f7fa;
  color: #007bff;
}

.error-message {
  background-color: #ffe0e0;
  color: #d32f2f;
}

.no-data-message {
    background-color: #fff3e0;
    color: #f57c00;
}

.course-list ul {
  list-style: none;
  padding: 0;
}

.course-list li {
  background-color: #f9f9f9;
  border: 1px solid #eee;
  border-radius: 5px;
  padding: 15px;
  margin-bottom: 10px;
  cursor: pointer;
  transition: background-color 0.3s ease, border-color 0.3s ease;
}

.course-list li:hover {
  background-color: #e9e9e9;
  border-color: #c9c9c9;
}

.course-list li.selected {
  background-color: #e0f2f7;
  border-color: #007bff;
  box-shadow: 0 0 5px rgba(0, 123, 255, 0.3);
}

.course-list strong {
  color: #007bff;
}

.assessment-details {
  margin-top: 30px;
  padding: 20px;
  background-color: #f0f8ff;
  border: 1px solid #add8e6;
  border-radius: 8px;
}

table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 15px;
}

th, td {
  border: 1px solid #ddd;
  padding: 10px;
  text-align: left;
}

th {
  background-color: #e9ecef;
  color: #495057;
}

tbody tr:nth-child(even) {
  background-color: #f6f6f6;
}

.total-marks {
  margin-top: 20px;
  padding: 10px;
  background-color: #d4edda;
  border: 1px solid #28a745;
  border-radius: 5px;
  text-align: right;
  font-weight: bold;
  color: #155724;
}
</style>
