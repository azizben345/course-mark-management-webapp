<template>
  <div class="class-rank-container">
    <h2>My Class Rank & Percentile</h2>

    <!-- Loading, Error, No Data Messages for Courses -->
    <div v-if="loadingCourses" class="loading-message">Loading your courses...</div>
    <div v-else-if="courseError" class="error-message">Error: {{ courseError }}</div>
    <div v-else-if="enrollments.length === 0" class="no-data-message">
      You are not currently enrolled in any courses to view class rank.
    </div>

    <!-- Course Selection Dropdown -->
    <div v-else class="course-selection">
      <label for="selectCourseRank">Select a Course:</label>
      <select id="selectCourseRank" v-model="selectedEnrollmentId" @change="fetchClassRankData">
        <option value="">-- Select --</option>
        <option v-for="enrollment in enrollments" :key="enrollment.enrollment_id" :value="enrollment.enrollment_id">
          {{ enrollment.course_code }} - {{ enrollment.course_name }} ({{ enrollment.academic_year }})
        </option>
      </select>
    </div>

    <!-- Loading, Error, No Data Messages for Rank Data -->
    <div v-if="selectedEnrollmentId && loadingRank" class="loading-message">Loading class rank data...</div>
    <div v-else-if="selectedEnrollmentId && rankError" class="error-message">Error: {{ rankError }}</div>
    <div v-else-if="selectedEnrollmentId && !rankData" class="no-data-message">
      No class rank data available for this course yet.
    </div>

    <!-- Class Rank Details Display -->
    <div v-else-if="rankData" class="rank-details">
      <h3>Details for {{ selectedCourseName }}</h3>
      <div class="stat-card">
        <div class="stat-label">Your Final Total Mark:</div>
        <div class="stat-value">{{ rankData.your_final_total !== null ? rankData.your_final_total : 'N/A' }} / 100</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Your Class Rank:</div>
        <div class="stat-value">{{ rankData.your_rank !== null ? rankData.your_rank : 'N/A' }}</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Total Students in Class:</div>
        <div class="stat-value">{{ rankData.total_students !== null ? rankData.total_students : 'N/A' }}</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Your Percentile:</div>
        <div class="stat-value">{{ rankData.your_percentile !== null ? rankData.your_percentile.toFixed(2) + '%' : 'N/A' }}</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Class Average Mark:</div>
        <div class="stat-value">{{ rankData.class_average_final_total !== null ? rankData.class_average_final_total.toFixed(2) : 'N/A' }} / 100</div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'StudentClassRank',
  data() {
    return {
      userId: null,
      token: null,
      enrollments: [], // List of enrolled courses for the dropdown
      selectedEnrollmentId: '', // Currently selected enrollment
      rankData: null, // Stores the fetched rank and percentile data
      loadingCourses: true,
      loadingRank: false,
      courseError: '',
      rankError: ''
    };
  },
  computed: {
    // Dynamically gets the name of the selected course for display
    selectedCourseName() {
      const selected = this.enrollments.find(e => e.enrollment_id === this.selectedEnrollmentId);
      return selected ? `${selected.course_code} - ${selected.course_name}` : 'Selected Course';
    }
  },
  methods: {
    // Redirects to login page and clears local storage
    redirectToLogin() {
      localStorage.removeItem('jwt_token');
      localStorage.removeItem('user_info');
      if (this.$router) {
        this.$router.push('/');
      } else {
        window.location.href = 'login.html'; // Fallback
      }
    },
    // Fetches the list of enrollments for the logged-in student
    async fetchEnrollments() {
      this.loadingCourses = true;
      this.courseError = '';
      this.enrollments = [];
      const userInfoString = localStorage.getItem('user_info');
      this.token = localStorage.getItem('jwt_token'); // Get token for API calls

      // Check for authentication data
      if (!this.token || !userInfoString) {
        this.courseError = "Authentication data missing. Please log in again.";
        this.redirectToLogin();
        return;
      }

      let userInfo;
      try {
        userInfo = JSON.parse(userInfoString);
        this.userId = userInfo.id; // Extract user ID
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
          // Automatically select the first enrollment if available and fetch its rank data
          if (this.enrollments.length > 0) {
            this.selectedEnrollmentId = this.enrollments[0].enrollment_id;
            await this.fetchClassRankData();
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
    // Fetches the class rank data for the selected enrollment
    async fetchClassRankData() {
      if (!this.selectedEnrollmentId) {
        this.rankData = null;
        return;
      }

      this.loadingRank = true;
      this.rankError = '';

      if (!this.token) {
        this.rankError = "Authentication token missing. Please log in again.";
        this.redirectToLogin();
        return;
      }

      const API_ENDPOINT = `http://localhost:8000/api/enrollments/${this.selectedEnrollmentId}/rank`;

      try {
        const response = await fetch(API_ENDPOINT, {
          method: 'GET',
          headers: {
            'Authorization': `Bearer ${this.token}`,
            'Content-Type': 'application/json'
          }
        });

        if (response.ok) {
          this.rankData = await response.json();
          // Optional: Add a class for percentile value to style it differently
          if (this.rankData && this.rankData.your_percentile !== null) {
            // No direct modification to the data, can be handled by template class binding
          }
        } else if (response.status === 401 || response.status === 403) {
          this.rankError = 'Session expired or unauthorized to view this data.';
          this.redirectToLogin();
        } else {
          const errorResult = await response.json();
          this.rankError = `Failed to fetch class rank data: ${errorResult.error || 'Unknown error'}`;
          this.rankData = null; // Clear data on error
        }
      } catch (error) {
        console.error("Network or Fetch Error for class rank:", error);
        this.rankError = 'Network error. Could not load class rank data.';
        this.rankData = null; // Clear data on error
      } finally {
        this.loadingRank = false;
      }
    }
  },
  created() {
    this.fetchEnrollments(); // Fetch enrollments on component creation
  }
};
</script>

<style scoped>
/* Main container styling */
.class-rank-container {
  max-width: 800px;
  margin: 20px auto;
  padding: 25px;
  background-color: #fff;
  border-radius: 10px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
  font-family: 'Inter', sans-serif;
  color: #333;
}

/* Heading styles */
h2 {
  text-align: center;
  color: #2c3e50;
  margin-bottom: 30px;
  font-size: 2.2em;
  font-weight: 700;
  border-bottom: 2px solid #e0e0e0;
  padding-bottom: 15px;
}

h3 {
  color: #34495e;
  margin-top: 35px;
  margin-bottom: 25px;
  font-size: 1.6em;
  font-weight: 600;
  text-align: center;
}

/* Message box styles (loading, error, no data) */
.loading-message, .error-message, .no-data-message {
  padding: 15px;
  margin-bottom: 15px;
  border-radius: 8px;
  text-align: center;
  font-weight: bold;
  font-size: 1.1em;
}

.loading-message {
  background-color: #e0f7fa; /* Light blue */
  color: #007bff; /* Primary blue */
}

.error-message {
  background-color: #ffe0e0; /* Light red */
  color: #d32f2f; /* Dark red */
}

.no-data-message {
  background-color: #fff3e0; /* Light orange */
  color: #f57c00; /* Dark orange */
}

/* Course selection dropdown styling */
.course-selection {
  margin-bottom: 30px;
  text-align: center;
}

.course-selection label {
  font-size: 1.1em;
  margin-right: 15px;
  color: #555;
  font-weight: 500;
}

.course-selection select {
  padding: 10px 15px;
  border: 1px solid #ccc;
  border-radius: 8px;
  font-size: 1em;
  background-color: #f8f8f8;
  cursor: pointer;
  box-shadow: inset 0 1px 3px rgba(0,0,0,0.05);
  min-width: 250px;
}

.course-selection select:focus {
  border-color: #007bff;
  box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
  outline: none;
}

/* Rank details grid and cards */
.rank-details {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); /* Responsive grid columns */
  gap: 20px; /* Space between cards */
  margin-top: 30px;
  padding-top: 20px;
  border-top: 1px dashed #e0e0e0; /* Separator line */
}

.stat-card {
  background-color: #f0f8ff; /* Very light blue */
  border: 1px solid #add8e6; /* Light blue border */
  border-radius: 8px;
  padding: 20px;
  text-align: center;
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.08); /* Subtle shadow */
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  transition: transform 0.2s ease-in-out; /* Smooth hover effect */
}

.stat-card:hover {
  transform: translateY(-5px); /* Lift card on hover */
}

.stat-label {
  font-size: 1.1em;
  color: #555;
  margin-bottom: 8px;
  font-weight: 500;
}

.stat-value {
  font-size: 2.5em; /* Large value */
  font-weight: bold;
  color: #007bff; /* Primary blue for values */
}

/* Specific color for percentile to make it stand out */
.stat-value.percentile {
  color: #28a745; /* Green for good performance */
}

/* Responsive adjustments for smaller screens */
@media (max-width: 600px) {
  .rank-details {
    grid-template-columns: 1fr; /* Stack cards vertically on very small screens */
  }
}
</style>
