export interface User {
  id: number;
  name: string;
  email: string;
  email_verified_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface Post {
  id: number;
  user_id: number;
  title: string;
  content: string;
  published: boolean;
  created_at: string;
  updated_at: string;
  user?: {
    id: number;
    name: string;
    email: string;
  };
}

export interface AuthResponse {
  message: string;
  user: User;
  token: string;
}

export interface LoginCredentials {
  login: string;
  password: string;
}

export interface RegisterCredentials {
  name: string;
  username: string;
  email: string;
  password: string;
  password_confirmation: string;
  store_id: number;
}

export interface PaginatedResponse<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface PostFormData {
  title: string;
  content: string;
  published?: boolean;
}
