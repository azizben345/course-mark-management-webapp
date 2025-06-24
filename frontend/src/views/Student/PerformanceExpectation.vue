<template>
  <div class="what-if-tool-container">
    <h2>Academic Performance Expectation What-If Tool</h2>

    <!-- Loading, Error, No Data Messages for Courses -->
    <div v-if="loadingCourses" class="loading-message">Loading your courses...</div>
    <div v-else-if="courseError" class="error-message">Error: {{ courseError }}</div>
    <div v-else-if="enrollments.length === 0" class="no-data-message">
      You are not currently enrolled in any courses to use the what-if tool.
    </div>

    <!-- Course Selection Dropdown -->
    <div v-else class="course-selection">
      <label for="selectCourse">Select a Course:</label>
      <select id="selectCourse" v-model="selectedEnrollmentId" @change="fetchAssessmentData">
        <option value="">-- Select --</option>
        <option v-for="enrollment in enrollments" :key="enrollment.enrollment_id" :value="enrollment.enrollment_id">
          {{ enrollment.course_code }} - {{ enrollment.course_name }} ({{ enrollment.academic_year }})
        </option>
      </select>
    </div>

    <!-- What-If Input Area -->
    <div v-if="selectedEnrollmentId && loadingAssessments" class="loading-message">Loading assessment components...</div>
    <div v-else-if="selectedEnrollmentId && assessmentError" class="error-message">Error: {{ assessmentError }}</div>
    <div v-else-if="selectedEnrollmentId && assessmentData.components.length === 0" class="no-data-message">
      No assessment components found for this course.
    </div>
    <div v-else-if="selectedEnrollmentId && assessmentData">
      <section class="what-if-inputs">
        <h3>Input Hypothetical Marks</h3>
        <div class="component-list">
          <div v-for="comp in assessmentData.components" :key="comp.component_id" class="component-item">
            <label :for="'mark-' + comp.component_id">{{ comp.component_name }} (Max: {{ comp.max_mark }})</label>
            <input
              type="number"
              :id="'mark-' + comp.component_id"
              v-model.number="hypotheticalMarks[comp.component_id]"
              :placeholder="comp.mark_obtained !== null ? 'Current: ' + comp.mark_obtained : 'Enter mark'"
              :min="0"
              :max="comp.max_mark"
              :disabled="comp.mark_obtained !== null"
              class="mark-input"
            />
             <span v-if="comp.mark_obtained !== null" class="current-mark-label">Already Obtained: {{ comp.mark_obtained }}</span>
          </div>

          <!-- Final Exam Mark Input -->
          <div class="component-item final-exam-item">
            <label for="finalExamMark">Final Exam Mark (Max: 30)</label>
            <input
              type="number"
              id="finalExamMark"
              v-model.number="hypotheticalFinalExamMark"
              :placeholder="assessmentData.your_final_exam_mark !== null ? 'Current: ' + assessmentData.your_final_exam_mark : 'Enter mark'"
              :min="0"
              :max="30"
              :disabled="assessmentData.your_final_exam_mark !== null"
              class="mark-input"
            />
            <span v-if="assessmentData.your_final_exam_mark !== null" class="current-mark-label">Already Obtained: {{ assessmentData.your_final_exam_mark }}</span>
          </div>
        </div>

        <button @click="calculateProjection" :disabled="calculatingProjection || !selectedEnrollmentId" class="calculate-button">
          {{ calculatingProjection ? 'Calculating...' : 'Calculate Projection' }}
        </button>
      </section>

      <!-- Projection Results -->
      <section class="projection-results" v-if="projectionData">
        <h3>Projected Results</h3>
        <div class="result-card">
          <div class="result-label">Projected Continuous Assessment (CA) Total:</div>
          <div class="result-value">{{ projectionData.projected_total_ca !== null ? projectionData.projected_total_ca.toFixed(2) : 'N/A' }} / 70</div>
        </div>
        <div class="result-card">
          <div class="result-label">Projected Final Exam Mark:</div>
          <div class="result-value">{{ projectionData.projected_final_exam_mark !== null ? projectionData.projected_final_exam_mark.toFixed(2) : 'N/A' }} / 30</div>
        </div>
        <div class="result-card primary-result">
          <div class="result-label">Projected Overall Final Total:</div>
          <div class="result-value">{{ projectionData.projected_final_total !== null ? projectionData.projected_final_total.toFixed(2) : 'N/A' }} / 100</div>
        </div>
        <div class="result-card">
          <div class="result-label">Projected Grade:</div>
          <div class="result-value">{{ projectionData.projected_grade || 'N/A' }}</div>
        </div>
      </section>
      <div v-if="projectionError" class="error-message projection-error">Error: {{ projectionError }}</div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'PerformanceExpectation', // Name based on your request
  data() {
    return {
      userId: null,
      token: null,
      enrollments: [],
      selectedEnrollmentId: '',
      assessmentData: { components: [] }, // Stores fetched assessment components and student's current marks
      hypotheticalMarks: {}, // Stores hypothetical marks for each component by component_id
      hypotheticalFinalExamMark: null, // Stores hypothetical final exam mark
      projectionData: null, // Stores the results from the what-if calculation API
      loadingCourses: true,
      loadingAssessments: false,
      calculatingProjection: false,
      courseError: '',
      assessmentError: '',
      projectionError: '',
    };
  },
  methods: {
    // Utility to redirect to login if session is invalid
    redirectToLogin() {
      localStorage.removeItem('jwt_token');
      localStorage.removeItem('user_info');
      if (this.$router) {
        this.$router.push('/');
      } else {
        window.location.href = 'login.html';
      }
    },
    // Fetches all enrollments for the logged-in student
    async fetchEnrollments() {
      this.loadingCourses = true;
      this.courseError = '';
      this.enrollments = [];
      const userInfoString = localStorage.getItem('user_info');
      this.token = localStorage.getItem('jwt_token');

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
          if (this.enrollments.length > 0) {
            this.selectedEnrollmentId = this.enrollments[0].enrollment_id;
            await this.fetchAssessmentData(); // Auto-fetch for the first course
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
    // Fetches assessment components and current marks for the selected enrollment
    async fetchAssessmentData() {
      if (!this.selectedEnrollmentId) {
        this.assessmentData = { components: [] };
        this.hypotheticalMarks = {};
        this.hypotheticalFinalExamMark = null;
        this.projectionData = null;
        return;
      }

      this.loadingAssessments = true;
      this.assessmentError = '';
      this.projectionData = null; // Clear previous projection when course changes

      if (!this.token) {
        this.assessmentError = "Authentication token missing. Please log in again.";
        this.redirectToLogin();
        return;
      }

      // This endpoint fetches assessment components and student's current marks
      const API_ENDPOINT = `http://localhost:8000/api/enrollments/${this.selectedEnrollmentId}/components-marks`;

      try {
        const response = await fetch(API_ENDPOINT, {
          method: 'GET',
          headers: {
            'Authorization': `Bearer ${this.token}`,
            'Content-Type': 'application/json'
          }
        });

        if (response.ok) {
          const data = await response.json();
          this.assessmentData = data;
          this.initializeHypotheticalMarks(); // Set up hypothetical marks based on fetched data
        } else if (response.status === 401 || response.status === 403) {
          this.assessmentError = 'Session expired or unauthorized to view this data.';
          this.redirectToLogin();
        } else {
          const errorResult = await response.json();
          this.assessmentError = `Failed to fetch assessment data: ${errorResult.error || 'Unknown error'}`;
          this.assessmentData = { components: [] };
        }
      } catch (error) {
        console.error("Network or Fetch Error for assessment data:", error);
        this.assessmentError = 'Network error. Could not load assessment data.';
        this.assessmentData = { components: [] };
      } finally {
        this.loadingAssessments = false;
      }
    },
    // Initializes hypotheticalMarks with existing marks or null if not obtained
    initializeHypotheticalMarks() {
      this.hypotheticalMarks = {};
      if (this.assessmentData && this.assessmentData.components) {
        this.assessmentData.components.forEach(comp => {
          // If mark is obtained, use it; otherwise, default to null for user input
          this.hypotheticalMarks[comp.component_id] = comp.mark_obtained !== null ? comp.mark_obtained : null;
        });
      }
      // Initialize hypothetical final exam mark
      this.hypotheticalFinalExamMark = this.assessmentData.your_final_exam_mark !== null ? this.assessmentData.your_final_exam_mark : null;
    },
    // Sends hypothetical marks to backend to calculate projection
    async calculateProjection() {
      if (!this.selectedEnrollmentId) {
        this.projectionError = 'Please select a course first.';
        return;
      }

      this.calculatingProjection = true;
      this.projectionError = '';
      this.projectionData = null;

      const payload = {
        hypothetical_component_marks: {},
        hypothetical_final_exam_mark: this.hypotheticalFinalExamMark !== null ? this.hypotheticalFinalExamMark : null
      };

      // Populate hypothetical_component_marks, only including components that can be adjusted (not yet obtained)
      this.assessmentData.components.forEach(comp => {
        if (comp.mark_obtained === null) { // Only send hypothetical for unobtained marks
          const inputMark = this.hypotheticalMarks[comp.component_id];
          if (inputMark !== null && inputMark !== '') { // Ensure mark is provided and not empty
            payload.hypothetical_component_marks[comp.component_id] = parseFloat(inputMark);
          }
        }
      });

      // API endpoint for performance expectation calculation
      const API_ENDPOINT = `http://localhost:8000/api/enrollments/${this.selectedEnrollmentId}/performance-expectation`;

      try {
        const response = await fetch(API_ENDPOINT, {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${this.token}`,
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(payload)
        });

        if (response.ok) {
          this.projectionData = await response.json();
        } else if (response.status === 401 || response.status === 403) {
          this.projectionError = 'Session expired or unauthorized to perform this calculation.';
          this.redirectToLogin();
        } else {
          const errorResult = await response.json();
          this.projectionError = `Calculation failed: ${errorResult.error || 'Unknown error'}`;
          this.projectionData = null;
        }
      } catch (error) {
        console.error("Network or Fetch Error for performance expectation calculation:", error);
        this.projectionError = 'Network error. Could not perform what-if calculation.';
        this.projectionData = null;
      } finally {
        this.calculatingProjection = false;
      }
    }
  },
  created() {
    this.fetchEnrollments(); // Start by fetching enrollments
  },
  watch: {
    // Watch for changes in selectedEnrollmentId to reset and fetch new assessment data
    selectedEnrollmentId: function(newVal, oldVal) {
      if (newVal !== oldVal && newVal) {
        this.fetchAssessmentData();
      }
    }
  }
};
</script>

<style scoped>
.what-if-tool-container {
  max-width: 900px;
  margin: 20px auto;
  padding: 25px;
  background-color: #fff;
  border-radius: 10px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
  font-family: 'Inter', sans-serif;
  color: #333;
}

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
  margin-bottom: 20px;
  font-size: 1.6em;
  font-weight: 600;
}

/* Message box styles */
.loading-message, .error-message, .no-data-message, .projection-error {
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

.projection-error {
    background-color: #fce8e8; /* Lighter error background */
    color: #cc0000;
    border: 1px solid #cc0000;
    margin-top: 20px;
}

/* Course selection dropdown */
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

/* What-If Input Area */
.what-if-inputs {
  background-color: #f8fafd;
  padding: 25px;
  border-radius: 10px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  margin-top: 20px;
}

.component-list {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 15px 20px;
  margin-bottom: 30px;
}

.component-item {
  display: flex;
  flex-direction: column;
  gap: 5px;
  background-color: #e9f5fe; /* Light blue background for each item */
  border: 1px solid #cce7ff;
  border-radius: 8px;
  padding: 15px;
}

.component-item label {
  font-weight: 500;
  color: #34495e;
  font-size: 1em;
}

.mark-input {
  padding: 8px 12px;
  border: 1px solid #a0cff7;
  border-radius: 6px;
  font-size: 1em;
  width: 100%;
  box-sizing: border-box; /* Include padding in width */
}

.mark-input:focus {
  border-color: #007bff;
  box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.2);
  outline: none;
}

.mark-input:disabled {
  background-color: #e9ecef;
  color: #6c757d;
  cursor: not-allowed;
}

.current-mark-label {
    font-size: 0.85em;
    color: #6c757d;
    margin-top: 4px;
    font-style: italic;
}

.final-exam-item {
  grid-column: 1 / -1; /* Make final exam span full width in grid */
  background-color: #dbeaff; /* Slightly different shade for final exam */
  border-color: #a3c2e6;
}

.calculate-button {
  display: block;
  width: auto; /* Auto width based on content */
  min-width: 200px; /* Minimum width for the button */
  padding: 12px 25px;
  margin: 0 auto; /* Center the button */
  background-color: #28a745; /* Green button */
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 1.1em;
  font-weight: 600;
  cursor: pointer;
  transition: background-color 0.3s ease, transform 0.2s ease;
  box-shadow: 0 4px 8px rgba(40, 167, 69, 0.2);
}

.calculate-button:hover {
  background-color: #218838;
  transform: translateY(-2px);
}

.calculate-button:disabled {
  background-color: #cccccc;
  cursor: not-allowed;
  box-shadow: none;
  transform: none;
}

/* Projection Results */
.projection-results {
  background-color: #eaf7ed; /* Light green background */
  padding: 25px;
  border-radius: 10px;
  box-shadow: 0 4px 15px rgba(0, 123, 255, 0.1);
  margin-top: 40px;
  border: 1px solid #82e0aa;
}

.result-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 0;
  border-bottom: 1px dashed #c0e6c0; /* Light green dashed border */
}

.result-card:last-child {
  border-bottom: none;
}

.result-label {
  font-size: 1.1em;
  color: #34495e;
  font-weight: 500;
}

.result-value {
  font-size: 1.4em;
  font-weight: bold;
  color: #007bff; /* Primary blue */
}

.primary-result .result-value {
  font-size: 1.8em;
  color: #28a745; /* Green for overall total */
}

/* Responsive adjustments */
@media (max-width: 768px) {
  .what-if-tool-container {
    padding: 15px;
  }
  .component-list {
    grid-template-columns: 1fr; /* Stack components on small screens */
  }
  .final-exam-item {
    grid-column: auto; /* Reset grid column for final exam on small screens */
  }
}
</style>
