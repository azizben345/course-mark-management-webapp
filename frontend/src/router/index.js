// router/index.js
import { createRouter, createWebHashHistory } from 'vue-router';

// Universal Dashboard
import MyDashboard from '../views/MyDashboard.vue';

// General & Role-Specific Dashboards
import MyLogin from '../views/MyLogin.vue';
// import DashboardLecturer from '../views/DashboardLecturer.vue';
// import DashboardStudent from '../views/DashboardStudent.vue';
// import DashboardAdvisor from '../views/DashboardAdvisor.vue';

// // Lecturer Views
// import ManageStudents from '../views/ManageStudents.vue';
// import AssessmentEntry from '../views/AssessmentEntry.vue';
// import FinalExamEntry from '../views/FinalExamEntry.vue';
// import Analytics from '../views/Analytics.vue';
// import ExportCSV from '../views/ExportCSV.vue';
// import Notification from '../views/Notification.vue';

// // Student Views
// import ProgressViewer from '../views/ProgressViewer.vue';
// import MarkComparison from '../views/MarkComparison.vue';
// import StudentRanking from '../views/StudentRanking.vue';
// import WhatIfSimulator from '../views/WhatIfSimulator.vue';
// import RemarkRequest from '../views/RemarkRequest.vue';

// // Advisor Views
// import AdviseeList from '../views/AdviseeList.vue';
// import AdviseeDetails from '../views/AdviseeDetails.vue';
// import AtRiskHighlights from '../views/AtRiskHighlights.vue';
// import AdvisorNotes from '../views/AdvisorNotes.vue';
// import ExportConsultations from '../views/ExportConsultations.vue';

// // Admin (Optional)
// import AdminPanel from '../views/AdminPanel.vue';

// import NotFound from '../views/NotFound.vue';

const routes = [
  { path: '/', component: MyLogin },

  // Dashboards
  { path: '/dashboard', component: MyDashboard },
  // { path: '/lecturer', component: DashboardLecturer },
  // { path: '/lecturer', component: DashboardLecturer },
  // { path: '/student', component: DashboardStudent },
  // { path: '/advisor', component: DashboardAdvisor },

  // // Lecturer Routes
  // { path: '/lecturer/manage-students', component: ManageStudents },
  // { path: '/lecturer/assessments', component: AssessmentEntry },
  // { path: '/lecturer/final-exam', component: FinalExamEntry },
  // { path: '/lecturer/analytics', component: Analytics },
  // { path: '/lecturer/export', component: ExportCSV },
  // { path: '/lecturer/notify', component: Notification },

  // // Student Routes
  // { path: '/student/progress', component: ProgressViewer },
  // { path: '/student/comparison', component: MarkComparison },
  // { path: '/student/ranking', component: Ranking },
  // { path: '/student/what-if', component: WhatIfSimulator },
  // { path: '/student/remark', component: RemarkRequest },

  // // Advisor Routes
  // { path: '/advisor/advisees', component: AdviseeList },
  // { path: '/advisor/details/:id', component: AdviseeDetails, props: true },
  // { path: '/advisor/risk', component: AtRiskHighlights },
  // { path: '/advisor/notes/:id', component: AdvisorNotes, props: true },
  // { path: '/advisor/exports', component: ExportConsultations },

  // // Admin (Optional)
  // { path: '/admin', component: AdminPanel },

  // // 404
  // { path: '/:pathMatch(.*)*', name: 'NotFound', component: NotFound }
];

const router = createRouter({
  history: createWebHashHistory(),
  routes
});

export default router;
