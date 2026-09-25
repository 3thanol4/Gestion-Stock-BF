import { Routes } from '@angular/router';
import { LoginComponent } from './login/login';
import { RegisterComponent } from './register/register';
import { DashboardComponent } from './dashboard/dashboard';
import { EditUserComponent } from './edit-user/edit-user';

export const routes: Routes = [
  { path: 'login', component: LoginComponent },
  { path: 'register', component: RegisterComponent },
  { path: 'dashboard', component: DashboardComponent },
  { path: 'edit-user/:id', component: EditUserComponent },
  { path: '', redirectTo: '/login', pathMatch: 'full' }
];
