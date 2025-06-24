<template>
  <div class="mark-comparison-container">
    <h2>Compare Marks with Coursemates</h2>

    <!-- Loading, Error, No Data Messages for Courses -->
    <div v-if="loadingCourses" class="loading-message">Loading your courses...</div>
    <div v-else-if="courseError" class="error-message">Error: {{ courseError }}</div>
    <div v-else-if="enrollments.length === 0" class="no-data-message">
      You are not currently enrolled in any courses to compare marks.
    </div>

    <!-- Course Selection Dropdown -->
    <div v-else class="course-selection">
      <label for="selectCourse">Select a Course:</label>
      <select id="selectCourse" v-model="selectedEnrollmentId" @change="fetchComparisonData">
        <option value="">-- Select --</option>
        <option v-for="enrollment in enrollments" :key="enrollment.enrollment_id" :value="enrollment.enrollment_id">
          {{ enrollment.course_code }} - {{ enrollment.course_name }} ({{ enrollment.academic_year }})
        </option>
      </select>
    </div>

    <!-- Loading, Error, No Data Messages for Comparison Data -->
    <div v-if="selectedEnrollmentId && loadingComparison" class="loading-message">Loading comparison data...</div>
    <div v-else-if="selectedEnrollmentId && comparisonError" class="error-message">Error: {{ comparisonError }}</div>
    <div v-else-if="selectedEnrollmentId && !comparisonData" class="no-data-message">
      No comparison data available for this course yet.
    </div>

    <!-- Comparison Data Display (Table and Chart) -->
    <div v-else-if="comparisonData">
      <section class="comparison-table">
        <h3>Assessment Component Comparison</h3>
        <table>
          <thead>
            <tr>
              <th>Component Name</th>
              <th>Your Mark</th>
              <th>Max Mark</th>
              <th>Class Average</th>
              <th>Your Percentage</th>
              <th>Class Average Percentage</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="comp in comparisonData.components" :key="comp.component_id">
              <td>{{ comp.component_name }}</td>
              <td>{{ comp.your_mark !== null ? comp.your_mark : 'N/A' }}</td>
              <td>{{ comp.max_mark }}</td>
              <td>{{ comp.class_average !== null ? comp.class_average.toFixed(2) : 'N/A' }}</td>
              <td>{{ comp.your_mark !== null ? calculatePercentage(comp.your_mark, comp.max_mark).toFixed(2) + '%' : 'N/A' }}</td>
              <td>{{ comp.class_average !== null ? calculatePercentage(comp.class_average, comp.max_mark).toFixed(2) + '%' : 'N/A' }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <td><strong>Total Continuous Assessment</strong></td>
              <td><strong>{{ comparisonData.your_total_ca !== null ? comparisonData.your_total_ca : 'N/A' }}</strong></td>
              <td><strong>70</strong></td>
              <td><strong>{{ comparisonData.class_average_total_ca !== null ? comparisonData.class_average_total_ca.toFixed(2) : 'N/A' }}</strong></td>
              <td><strong>{{ comparisonData.your_total_ca !== null ? calculatePercentage(comparisonData.your_total_ca, 70).toFixed(2) + '%' : 'N/A' }}</strong></td>
              <td><strong>{{ comparisonData.class_average_total_ca !== null ? calculatePercentage(comparisonData.class_average_total_ca, 70).toFixed(2) + '%' : 'N/A' }}</strong></td>
            </tr>
             <tr>
              <td><strong>Final Exam Mark</strong></td>
              <td><strong>{{ comparisonData.your_final_exam_mark !== null ? comparisonData.your_final_exam_mark : 'N/A' }}</strong></td>
              <td><strong>30</strong></td>
              <td><strong>{{ comparisonData.class_average_final_exam_mark !== null ? comparisonData.class_average_final_exam_mark.toFixed(2) : 'N/A' }}</strong></td>
              <td><strong>{{ comparisonData.your_final_exam_mark !== null ? calculatePercentage(comparisonData.your_final_exam_mark, 30).toFixed(2) + '%' : 'N/A' }}</strong></td>
              <td><strong>{{ comparisonData.class_average_final_exam_mark !== null ? calculatePercentage(comparisonData.class_average_final_exam_mark, 30).toFixed(2) + '%' : 'N/A' }}</strong></td>
            </tr>
            <tr>
              <td><strong>Overall Final Total</strong></td>
              <td><strong>{{ comparisonData.your_final_total !== null ? comparisonData.your_final_total : 'N/A' }}</strong></td>
              <td><strong>100</strong></td>
              <td><strong>{{ comparisonData.class_average_final_total !== null ? comparisonData.class_average_final_total.toFixed(2) : 'N/A' }}</strong></td>
              <td><strong>{{ comparisonData.your_final_total !== null ? calculatePercentage(comparisonData.your_final_total, 100).toFixed(2) + '%' : 'N/A' }}</strong></td>
              <td><strong>{{ comparisonData.class_average_final_total !== null ? calculatePercentage(comparisonData.class_average_final_total, 100).toFixed(2) + '%' : 'N/A' }}</strong></td>
            </tr>
          </tfoot>
        </table>
      </section>

      <section class="comparison-chart" v-if="chartData.labels.length > 0">
        <h3>Visual Comparison</h3>
        <canvas ref="barChartCanvas"></canvas>
      </section>
    </div>
  </div>
</template>

<script>
import Chart from 'chart.js/auto'; // Import Chart.js library

export default {
  name: 'StudentMarkComparison',
  data() {
    return {
      userId: null,
      token: null,
      enrollments: [], // List of enrolled courses for the dropdown
      selectedEnrollmentId: '', // Currently selected enrollment
      comparisonData: null, // Stores the data fetched for comparison
      loadingCourses: true,
      loadingComparison: false,
      courseError: '',
      comparisonError: '',
      chartInstance: null // Holds the Chart.js instance for proper destruction
    };
  },
  computed: {
    // Prepares data in Chart.js format
    chartData() {
      if (!this.comparisonData) {
        return { labels: [], datasets: [] };
      }

      // Extract component names, your marks, class averages, and max marks
      const labels = this.comparisonData.components.map(comp => comp.component_name);
      const yourMarks = this.comparisonData.components.map(comp => comp.your_mark !== null ? comp.your_mark : 0);
      const classAverages = this.comparisonData.components.map(comp => comp.class_average !== null ? comp.class_average : 0);
      const maxMarks = this.comparisonData.components.map(comp => comp.max_mark);

      // Add overall totals to the chart labels and data for a complete view
      labels.push('Total CA');
      yourMarks.push(this.comparisonData.your_total_ca !== null ? this.comparisonData.your_total_ca : 0);
      classAverages.push(this.comparisonData.class_average_total_ca !== null ? this.comparisonData.class_average_total_ca : 0);
      maxMarks.push(70); // Max for CA is 70

      labels.push('Final Exam');
      yourMarks.push(this.comparisonData.your_final_exam_mark !== null ? this.comparisonData.your_final_exam_mark : 0);
      classAverages.push(this.comparisonData.class_average_final_exam_mark !== null ? this.comparisonData.class_average_final_exam_mark : 0);
      maxMarks.push(30); // Max for Final Exam is 30

      labels.push('Overall Total');
      yourMarks.push(this.comparisonData.your_final_total !== null ? this.comparisonData.your_final_total : 0);
      classAverages.push(this.comparisonData.class_average_final_total !== null ? this.comparisonData.class_average_final_total : 0);
      maxMarks.push(100); // Max for Overall Total is 100


      return {
        labels: labels,
        datasets: [
          {
            label: 'Your Mark',
            backgroundColor: 'rgba(54, 162, 235, 0.6)', // Blue bars
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1,
            data: yourMarks,
            maxBarThickness: 50, // Limits the width of individual bars
          },
          {
            label: 'Class Average',
            backgroundColor: 'rgba(255, 159, 64, 0.6)', // Orange bars
            borderColor: 'rgba(255, 159, 64, 1)',
            borderWidth: 1,
            data: classAverages,
            maxBarThickness: 50, // Limits the width of individual bars
          }
        ],
        maxMarks: maxMarks // This array will be used in the Chart.js tooltip callback
      };
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
        window.location.href = 'login.html'; // Fallback for non-Vue router environments
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
          // Automatically select the first enrollment if available and fetch its comparison data
          if (this.enrollments.length > 0) {
            this.selectedEnrollmentId = this.enrollments[0].enrollment_id;
            await this.fetchComparisonData();
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
    // Fetches the comparison data for the selected enrollment
    async fetchComparisonData() {
      if (!this.selectedEnrollmentId) {
        this.comparisonData = null;
        return;
      }

      this.loadingComparison = true;
      this.comparisonError = '';

      if (!this.token) {
        this.comparisonError = "Authentication token missing. Please log in again.";
        this.redirectToLogin();
        return;
      }

      const API_ENDPOINT = `http://localhost:8000/api/enrollments/${this.selectedEnrollmentId}/comparison`;

      try {
        const response = await fetch(API_ENDPOINT, {
          method: 'GET',
          headers: {
            'Authorization': `Bearer ${this.token}`,
            'Content-Type': 'application/json'
          }
        });

        if (response.ok) {
          this.comparisonData = await response.json();
          // Use $nextTick to ensure DOM (canvas) is updated before rendering chart
          this.$nextTick(() => {
            this.renderChart();
          });
        } else if (response.status === 401 || response.status === 403) {
          this.comparisonError = 'Session expired or unauthorized to view this comparison.';
          this.redirectToLogin();
        } else {
          const errorResult = await response.json();
          this.comparisonError = `Failed to fetch comparison data: ${errorResult.error || 'Unknown error'}`;
          this.comparisonData = null; // Clear data on error
        }
      } catch (error) {
        console.error("Network or Fetch Error for comparison:", error);
        this.comparisonError = 'Network error. Could not load comparison data.';
        this.comparisonData = null; // Clear data on error
      } finally {
        this.loadingComparison = false;
      }
    },
    // Calculates percentage given obtained and max marks
    calculatePercentage(obtained, max) {
      if (max === 0 || obtained === null) return 0;
      return (parseFloat(obtained) / parseFloat(max)) * 100;
    },
    // Renders or updates the Chart.js bar chart
    renderChart() {
      // Destroy existing chart instance to prevent memory leaks and conflicts
      if (this.chartInstance) {
        this.chartInstance.destroy();
      }

      const ctx = this.$refs.barChartCanvas;
      if (!ctx) {
        console.warn('Canvas element not found for chart rendering.');
        return;
      }

      // Determine the maximum value for the y-axis (round up to nearest 10 for better scaling)
      const allValues = [...this.chartData.datasets[0].data, ...this.chartData.datasets[1].data];
      const maxDataValue = Math.max(...allValues);
      const yAxisMax = maxDataValue > 0 ? Math.ceil(maxDataValue / 10) * 10 : 100; // Default to 100 if no data or max is 0

      this.chartInstance = new Chart(ctx, {
        type: 'bar', // Bar chart type
        data: this.chartData, // Data prepared in computed property
        options: {
          responsive: true,
          maintainAspectRatio: false, // Allows the chart to fill its container
          indexAxis: 'x', // 'x' for vertical bars, 'y' for horizontal
          plugins: {
            tooltip: {
              callbacks: {
                label: function(context) {
                  let label = context.dataset.label || '';
                  if (label) {
                    label += ': ';
                  }
                  if (context.parsed.y !== null) {
                    // Custom tooltip to show "Mark / Max Mark"
                    label += context.parsed.y + ' / ' + (context.chart.data.maxMarks[context.dataIndex] || 'Max');
                  }
                  return label;
                }
              }
            }
          },
          scales: {
            x: {
              title: {
                display: true,
                text: 'Assessment Item'
              },
              grid: {
                display: false // Hide x-axis grid lines for cleaner look
              }
            },
            y: {
              beginAtZero: true,
              max: yAxisMax, // Dynamic max for y-axis
              title: {
                display: true,
                text: 'Marks'
              },
              ticks: {
                stepSize: 10 // Y-axis ticks every 10 marks
              }
            }
          }
        }
      });
    }
  },
  created() {
    this.fetchEnrollments(); // Fetch enrollments on component creation
  },
  watch: {
    // Watch for changes in comparisonData to re-render chart if data updates (e.g., student selects new course)
    comparisonData: {
      handler(newVal) { // Removed oldVal as it's not used
        // Only re-render if there's actual data and a canvas element
        if (newVal && this.$refs.barChartCanvas) {
          this.renderChart();
        } else if (!newVal && this.chartInstance) {
          // If data becomes null (e.g., error, no data), destroy chart
          this.chartInstance.destroy();
          this.chartInstance = null;
        }
      },
      deep: true // Watch for nested changes within the comparisonData object
    }
  },
  beforeUnmount() {
    // Crucial: Destroy Chart.js instance before component is removed to prevent memory leaks
    if (this.chartInstance) {
      this.chartInstance.destroy();
    }
  }
};
</script>

<style scoped>
/* Main container styling */
.mark-comparison-container {
  max-width: 1000px;
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
  margin-bottom: 20px;
  font-size: 1.6em;
  font-weight: 600;
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

/* Comparison table styling */
.comparison-table table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 20px;
  background-color: #fdfdfd;
  border-radius: 8px;
  overflow: hidden; /* Ensures rounded corners on table */
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.comparison-table th, .comparison-table td {
  border: 1px solid #e9ecef;
  padding: 12px 15px;
  text-align: left;
  font-size: 0.95em;
}

.comparison-table thead th {
  background-color: #007bff; /* Blue header */
  color: white;
  font-weight: 600;
  text-transform: uppercase;
}

.comparison-table tbody tr:nth-child(even) {
  background-color: #f2f7fc; /* Light blue stripe */
}

.comparison-table tbody tr:hover {
  background-color: #e6f7ff; /* Lighter blue on hover */
}

.comparison-table tfoot td {
  background-color: #e9f5fe; /* Lighter blue for footer */
  font-weight: bold;
  border-top: 2px solid #007bff;
}

/* Chart container styling */
.comparison-chart {
  margin-top: 40px;
  background-color: #fdfdfd;
  padding: 25px;
  border-radius: 10px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  height: 400px; /* Fixed height for the chart container */
  display: flex;
  flex-direction: column;
  justify-content: center; /* Vertically center content if smaller than height */
}
</style>
