import {bootstrapApplication} from '@angular/platform-browser';
import {HttpResponse,provideHttpClient,withInterceptors} from '@angular/common/http';
import {provideRouter} from '@angular/router';
import {tap} from 'rxjs';
import {AppComponent} from './app/app.component';
import {routes} from './app/app.routes';

bootstrapApplication(AppComponent,{providers:[provideRouter(routes),provideHttpClient(withInterceptors([(req,next)=>{
 let outgoing=req.clone({withCredentials:true});
 if(['POST','PATCH','PUT','DELETE'].includes(req.method)&&typeof sessionStorage!=='undefined'){
  const token=sessionStorage.getItem('lirandzu_csrf');
  if(token)outgoing=outgoing.clone({setHeaders:{'X-CSRF-Token':token}});
 }
 return next(outgoing).pipe(tap(event=>{
  if(!(event instanceof HttpResponse)||typeof sessionStorage==='undefined')return;

  // The API response body is untyped at runtime; narrow its shape before
  // reading authentication fields so strict TypeScript compilation succeeds.
  const body=event.body as {token?:unknown;csrf_token?:unknown}|null;
  if(event.url?.includes('/api/auth/csrf')&&typeof body?.token==='string'&&body.token.length>0){
   sessionStorage.setItem('lirandzu_csrf',body.token);
  }
  if(event.url?.includes('/api/auth/login')&&typeof body?.csrf_token==='string'&&body.csrf_token.length>0){
   sessionStorage.setItem('lirandzu_csrf',body.csrf_token);
  }
  if(event.url?.includes('/api/auth/logout'))sessionStorage.removeItem('lirandzu_csrf');
 }));
}]))]}).catch(console.error);
