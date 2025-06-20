<template>
  <div>
    <h2>Manage Students</h2>

    <!-- Loop through each course -->
    <div v-for="course in courses" :key="course.course_code">
      <h3>{{ course.course_name }} ({{ course.course_code }})</h3>

      <table>
        <thead>
          <tr>
            <th>Matric No</th>
            <th>Student Name</th>
            <!-- Dynamically create columns for each assessment component -->
            <th v-for="assessment in course.components" :key="assessment.component_id">
              {{ assessment.component_name }}
            </th>
            <th>Final Exam Mark</th>
            <th>Total</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="student in course.students" :key="student.matric_no">
            <td>{{ student.matric_no }}</td>
            <td>{{ student.student_name }}</td>

            <!-- Dynamically display marks for each assessment -->
            <td v-for="assessment in course.components" :key="assessment.component_id">
              {{ getAssessmentMark(student, assessment.component_name) }}
            </td>

            <td>{{ student.final_exam_mark }}</td>
            <td>{{ student.final_total }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script>
export default {
  name: 'ManageStudents',
  data() {
    return {
      courses: []  // Array to store courses with students and assessments
    };
  },
  created() {
    this.fetchStudents();  // Fetch students when the component is created
  },
  methods: {
    async fetchStudents() {
      const lecturerId = localStorage.getItem('username');  // Get the lecturer's username (which acts as lecturer_id)

      if (!lecturerId) {
        console.error("No lecturer username found in localStorage");
        return;  // Exit if no lecturer_id is found
      }

      const response = await fetch(`http://localhost:8000/manage-students/${lecturerId}`);
      const data = await response.json();

      this.courses = data.courses;  // Assign courses data to the courses array
    },

    getAssessmentMark(student, component_name) {
      // Find the mark for the given assessment component for the student
      const mark = student.marks.find(mark => mark.component_name === component_name);
      return mark ? mark.mark_obtained : 'N/A';  // Return the mark or 'N/A' if not found
    }
  }
};
</script>

<style scoped>
table {
  width: 100%;
  border-collapse: collapse;
}

table th, table td {
  padding: 8px 12px;
  border: 1px solid #ddd;
  text-align: left;
}

table th {
  background-color: #f4f4f4;
}
</style>
