<template>
  <div class="student-progress-container">
    <h2>My Academic Progress</h2>

    <div v-if="loadingProgress" class="loading-message">Loading your progress data...</div>
    <div v-else-if="progressError" class="error-message">Error: {{ progressError }}</div>
    <div v-else-if="enrollments.length === 0" class="no-data-message">
      You are not currently enrolled in any courses with completed assessments.
    </div>
    <div v-else>
      <section class="overall-summary">
        <h3>Overall Performance Summary</h3>
        <p>Current Academic Year: <strong>{{ currentAcademicYear }}</strong></p>
        <p>Total Courses Enrolled: <strong>{{ enrollments.length }}</strong></p>
        <p>Courses with Final Marks: <strong>{{ coursesWithFinalMarksCount }}</strong></p>
        <p>Overall Average Mark: <strong>{{ overallAverageMark.toFixed(2) }}%</strong></p>

        <div class="progress-bar-container">
          <div class="progress-bar-label">Overall Progress</div>
          <div class="progress-bar">
            <div
              class="progress-fill"
              :style="{ width: overallAverageMark + '%' }"
              :class="{ 'high-achiever': overallAverageMark >= 75 }"
            >
              {{ overallAverageMark.toFixed(2) }}%
            </div>
          </div>
        </div>
      </section>

      <section class="course-breakdown">
        <h3>Course-wise Assessment Breakdown</h3>
        <div class="course-cards">
          <div v-for="enrollment in enrollments" :key="enrollment.enrollment_id" class="course-card">
            <h4>{{ enrollment.course_code }} - {{ enrollment.course_name }}</h4>
            <p>Lecturer: {{ enrollment.lecturer_name }}</p>
            <p>Academic Year: {{ enrollment.academic_year }}</p>
            <p>Final Exam Mark: {{ enrollment.final_exam_mark }} / 30</p>
            <p>Total Continuous Assessment (CA): {{ enrollment.total_ca }} / 70</p>
            <p class="final-total">Final Total: <strong>{{ enrollment.final_total }} / 100</strong></p>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>

<script>
export default {
  name: 'StudentProgress',
  data() {
    return {
      userId: null,
      token: null,
      enrollments: [],
      loadingProgress: true,
      progressError: '',
      currentAcademicYear: '' // To display the current academic year for context
    };
  },
  computed: {
    coursesWithFinalMarksCount() {
      // Count enrollments where final_total is greater than 0 (or some threshold)
      return this.enrollments.filter(e => parseFloat(e.final_total) > 0).length;
    },
    overallAverageMark() {
      if (this.enrollments.length === 0) return 0;

      let totalSumOfFinalMarks = 0;
      let countWithFinalMarks = 0;

      this.enrollments.forEach(enrollment => {
        // Only include courses with a non-zero final total in the average calculation
        if (parseFloat(enrollment.final_total) > 0) {
          totalSumOfFinalMarks += parseFloat(enrollment.final_total);
          countWithFinalMarks++;
        }
      });

      if (countWithFinalMarks === 0) return 0;
      return totalSumOfFinalMarks / countWithFinalMarks;
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
    async fetchStudentProgress() {
      this.loadingProgress = true;
      this.progressError = '';
      this.enrollments = [];

      this.token = localStorage.getItem('jwt_token');
      const userInfoString = localStorage.getItem('user_info');

      if (!this.token || !userInfoString) {
        this.progressError = "Authentication data missing. Please log in again.";
        this.redirectToLogin();
        return;
      }

      let userInfo;
      try {
        userInfo = JSON.parse(userInfoString);
        this.userId = userInfo.id;
      } catch (e) {
        this.progressError = "Error parsing user info. Please log in again.";
        this.redirectToLogin();
        return;
      }

      if (!this.userId) {
        this.progressError = "User ID not found. Cannot fetch progress.";
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
          // Optionally, set the current academic year from the latest enrollment
          if (this.enrollments.length > 0) {
            this.currentAcademicYear = this.enrollments[0].academic_year; // Assuming enrollments are somewhat ordered or you pick one
          }
        } else if (response.status === 401 || response.status === 403) {
          this.progressError = 'Session expired or unauthorized. Please log in.';
          this.redirectToLogin();
        } else {
          const errorResult = await response.json();
          this.progressError = `Failed to fetch progress data: ${errorResult.error || 'Unknown error'}`;
        }
      } catch (error) {
        console.error("Network or Fetch Error for student progress:", error);
        this.progressError = 'Network error. Could not load progress data.';
      } finally {
        this.loadingProgress = false;
      }
    }
  },
  created() {
    this.fetchStudentProgress();
  }
};
</script>

<style scoped>
.student-progress-container {
  max-width: 900px;
  margin: 20px auto;
  padding: 20px;
  background-color: #fff;
  border-radius: 8px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
  font-family: 'Inter', sans-serif;
}

h2 {
  text-align: center;
  color: #2c3e50;
  margin-bottom: 25px;
  font-size: 2em;
}

h3 {
  color: #34495e;
  margin-top: 30px;
  margin-bottom: 15px;
  border-bottom: 2px solid #ecf0f1;
  padding-bottom: 10px;
  font-size: 1.5em;
}

.loading-message, .error-message, .no-data-message {
  padding: 15px;
  margin-bottom: 15px;
  border-radius: 5px;
  text-align: center;
  font-weight: bold;
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

/* Overall Summary Section */
.overall-summary p {
  font-size: 1.1em;
  margin-bottom: 8px;
  color: #555;
}

.overall-summary strong {
  color: #28a745;
}

.progress-bar-container {
  margin-top: 25px;
  margin-bottom: 25px;
  background-color: #e9ecef;
  border-radius: 10px;
  overflow: hidden;
  box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
}

.progress-bar-label {
  text-align: center;
  padding: 8px;
  font-weight: bold;
  color: #343a40;
  font-size: 1.1em;
  background-color: #dee2e6;
  border-bottom: 1px solid #ced4da;
}

.progress-bar {
  height: 35px;
  background-color: #e9ecef;
  border-radius: 10px;
  position: relative;
}

.progress-fill {
  height: 100%;
  background-color: #007bff; /* Default blue for progress */
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: flex-end; /* Align text to the end */
  color: white;
  font-weight: bold;
  padding-right: 10px;
  transition: width 0.5s ease-in-out, background-color 0.3s ease;
  min-width: 30px; /* Ensure text is visible even for low progress */
}

.progress-fill.high-achiever {
  background-color: #28a745; /* Green for high scores */
}

/* Course Breakdown Section */
.course-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 20px;
  margin-top: 20px;
}

.course-card {
  background-color: #f0f8ff; /* Light blue background for cards */
  border: 1px solid #add8e6;
  border-radius: 8px;
  padding: 20px;
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.08);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.course-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 6px 12px rgba(0, 0, 0, 0.12);
}

.course-card h4 {
  color: #007bff;
  margin-top: 0;
  margin-bottom: 10px;
  font-size: 1.2em;
}

.course-card p {
  margin-bottom: 5px;
  color: #495057;
  font-size: 0.95em;
}

.course-card .final-total {
  font-size: 1.1em;
  font-weight: bold;
  color: #28a745; /* Green for final total */
  margin-top: 15px;
  padding-top: 10px;
  border-top: 1px dashed #ced4da;
}
</style>
