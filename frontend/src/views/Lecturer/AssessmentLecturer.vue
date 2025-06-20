<template>
  <div>
    <!-- Form to create/update enrollment -->
    <form @submit.prevent="createOrUpdateEnrollment">
      <label>Matric No:</label>
      <input v-model="enrollment.matric_no" type="text" />
      <label>Assessment Marks:</label>
      <div v-for="(assessment, index) in assessments" :key="index">
        <label>{{ assessment.component_name }}:</label>
        <input v-model="assessmentMarks[index]" type="number" />
      </div>
      <label>Final Exam Mark:</label>
      <input v-model="enrollment.final_exam_mark" type="number" />
      <button type="submit">Save</button>
    </form>
  </div>
</template>

<script>
export default {
  data() {
    return {
      enrollment: {
        matric_no: '',
        final_exam_mark: ''
      },
      assessments: [
        { component_id: 1, component_name: 'Assignment 1' },
        { component_id: 2, component_name: 'Quiz 1' }
      ],
      assessmentMarks: []  // For capturing input for each assessment
    };
  },
  methods: {
    async createOrUpdateEnrollment() {
      const data = {
        student_matric_no: this.enrollment.matric_no,
        course_code: 'SEEE3143',  // Example course code
        lecturer_id: localStorage.getItem('username'),  // Get lecturer_id from localStorage
        academic_year: '2024/2025',
        assessment_marks: this.assessmentMarks.map((mark, index) => ({
          component_id: this.assessments[index].component_id,
          mark_obtained: mark
        })),
        final_exam_mark: this.enrollment.final_exam_mark
      };

      // If creating, call the POST endpoint
      await fetch('http://localhost:8000/enrollments', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      });

      // Handle success or failure
    }
  }
};
</script>
