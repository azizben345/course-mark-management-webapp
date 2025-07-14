// router/index.js
import { createRouter, createWebHashHistory } from 'vue-router';

// Universal Views
import MyDashboard from '../views/MyDashboard.vue';
import MyRegister from '../views/MyRegister.vue';
import MyLogin from '../views/MyLogin.vue';

// Lecturer Views
import ManageStudents from '../views/Lecturer/ManageStudents.vue';
import AssessmentLecturerEntry from '../views/Lecturer/AssessmentLecturer.vue';
import EditEnrollment from '../views/Lecturer/EditEnrollment.vue';
import AssessmentEdit from '../views/Lecturer/EditComponent.vue';
import ManageComponent from '../views/Lecturer/ManageComponent.vue';

// Student Views
import StudentAssessment from '../views/Student/StudentAssessment.vue';
import StudentProgress from '../views/Student/StudentProgress.vue';
import StudentMarkComparison from '../views/Student/StudentMarkComparison.vue'; // NEW
import StudentClassRank from '../views/Student/StudentClassRank.vue'; // NEW
import PerformanceExpectation from '../views/Student/PerformanceExpectation.vue';

// Advisor Views
import StudentAdvisorList from '../views/Advisor/StudentAdvisorList.vue';
import MeetingRecord from '../views/Advisor/MeetingRecord.vue';
// import ManageCourses from '../views/Advisor/ManageCourses.vue'; // NEW

// Admin
import ManageUsers from '../views/Admin/ManageUsers.vue';
import AddCourse from '../views/Admin/AddCourse.vue';

const routes = [
  // Authentication Routes
  { path: '/', name: 'Login', component: MyLogin }, // Named the login route
  { path: '/register', name: 'Register', component: MyRegister }, // Named the register route

  // Dashboards - Protected route
  {
    path: '/dashboard',
    name: 'Dashboard',
    component: MyDashboard,
    meta: { requiresAuth: true } // Mark this route as requiring authentication
  },

  // Lecturer Routes - Protected routes
  { path: '/lecturer/manage-students', component: ManageStudents, meta: { requiresAuth: true, role: 'lecturer' } },
  { path: '/lecturer/assessments', component: AssessmentLecturerEntry, meta: { requiresAuth: true, role: 'lecturer' } },
  {
    path: '/edit-enrollment/:enrollment_id',
    name: 'editEnrollment',
    component: EditEnrollment,
    props: true,
    meta: { requiresAuth: true, role: 'lecturer' }
  },
  { path: '/lecturer/edit-assessment/:component_id', component: AssessmentEdit, props: true, meta: { requiresAuth: true, role: 'lecturer' } },
  { path: '/lecturer/manage-component/:component_id', component: ManageComponent, meta: { requiresAuth: true, role: 'lecturer' } },

  // Student Routes - Protected routes
  { path: '/student/assessment', name: 'StudentAssessment', component: StudentAssessment, meta: { requiresAuth: true, role: 'student' } },
  { path: '/student/performance-expectation', name: 'StudentPerformanceExpectation', component: PerformanceExpectation, meta: { requiresAuth: true, role: 'student' } },
  { path: '/student/progress', name: 'StudentProgress', component: StudentProgress, meta: { requiresAuth: true, role: 'student' } }, 
  { path: '/student/compare-marks', name: 'StudentCompareMarks', component: StudentMarkComparison, meta: { requiresAuth: true, role: 'student' }},
  { path: '/student/class-rank', name: 'StudentClassRank', component: StudentClassRank,meta: { requiresAuth: true, role: 'student' }},
  

  // Advisor Routes - Protected routes
  { path: '/advisor/student-advisor-list', component: StudentAdvisorList, meta: { requiresAuth: true, role: 'advisor' } },
  { path: '/advisor/meeting-records', component: MeetingRecord, meta: { requiresAuth: true, role: 'advisor' } },

  // Admin Routes - Protected routes
  { path: '/admin/manage-users', component: ManageUsers, meta: { requiresAuth: true, role: 'admin' } },
  { path: '/admin/create-course', component: AddCourse, meta: { requiresAuth: true, role: 'admin' } }
  
  // 404 Catch-all (uncomment when all routes are defined and tested)
  // { path: '/:pathMatch(.*)*', name: 'NotFound', component: NotFound }
];

const router = createRouter({
  history: createWebHashHistory(), // Uses hash mode
  routes
});

// Navigation Guard: Protect routes that require authentication and handle role-based access
router.beforeEach((to, from, next) => {
  const isAuthenticated = localStorage.getItem('jwt_token'); // Check if token exists
  const userInfoString = localStorage.getItem('user_info'); // Retrieve user_info string

  let userRole = null;
  if (userInfoString) {
    try {
      const userInfo = JSON.parse(userInfoString);
      userRole = userInfo.role; // Extract role
    } catch (e) {
      console.error("Error parsing user_info from localStorage:", e);
      // Clear storage if malformed, then redirect to login
      localStorage.removeItem('jwt_token');
      localStorage.removeItem('user_info');
      return next({ name: 'Login' });
    }
  }

  console.log(`Navigating to: ${to.path}, Requires Auth: ${to.meta.requiresAuth}, Is Authenticated: ${!!isAuthenticated}, User Role: ${userRole}`);

  // Scenario 1: User is trying to access login/register while already authenticated
  if ((to.name === 'Login' || to.name === 'Register') && isAuthenticated) {
    console.log('Authenticated user trying to access login/register. Redirecting to dashboard.');
    return next({ name: 'Dashboard' }); // Redirect to dashboard
  }

  // Scenario 2: User is trying to access a protected route
  if (to.meta.requiresAuth) {
    if (!isAuthenticated) {
      console.log('Accessing protected route without authentication. Redirecting to login.');
      return next({ name: 'Login' }); // Not authenticated, redirect to login
    }

    // Scenario 3: Check role if specified in meta
    // Admin role can bypass specific role checks
    if (to.meta.role && userRole !== to.meta.role && userRole !== 'admin') {
      console.log(`Access Denied: User role "${userRole}" cannot access "${to.path}" (requires "${to.meta.role}").`);
      // Optionally, redirect to a more appropriate dashboard or an access denied page
      alert(`Access Denied: You do not have the required role (${to.meta.role}) to view this page.`);
      return next({ name: 'Dashboard' }); // Redirect to dashboard or an access denied page
    }
  }

  // If none of the above conditions met, allow navigation
  console.log('Navigation allowed.');
  next();
});

export default router;
