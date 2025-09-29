# AidlY Frontend - Modern Customer Support Platform

A professional, fully-integrated Next.js frontend for the AidlY customer support platform, featuring a modern UI inspired by Freshdesk with complete backend API integration.

## Features

### Core Functionality
- **Complete Authentication System**: JWT-based auth with login, logout, and session management
- **Dashboard with Analytics**: Real-time KPIs, charts, and ticket statistics
- **Ticket Management**: Full CRUD operations, filtering, sorting, and status management
- **Customer Management**: Customer profiles, interaction history, and support timeline
- **Settings & Preferences**: User profiles, notifications, security, and appearance customization
- **Dark Mode Support**: System-aware theme switching with persistent preferences
- **Responsive Design**: Fully responsive across desktop, tablet, and mobile devices

### UI/UX Features
- Modern, clean design inspired by Freshdesk
- Smooth animations and transitions
- Real-time notifications
- Global search functionality
- Intuitive navigation with collapsible sidebar
- Professional color palette with consistent design system

## Tech Stack

- **Framework**: Next.js 15 with App Router
- **Language**: TypeScript
- **Styling**: Tailwind CSS v4
- **UI Components**: shadcn/ui (Radix UI primitives)
- **State Management**: Zustand
- **Data Fetching**: TanStack Query (React Query)
- **HTTP Client**: Axios
- **Charts**: Recharts
- **Date Handling**: date-fns
- **Icons**: Lucide React
- **Animations**: Tailwind animations
- **Theme**: next-themes

## Installation

1. **Clone and navigate to frontend**:
```bash
cd AidlY/frontend
```

2. **Install dependencies**:
```bash
npm install
```

3. **Configure environment variables**:
```bash
cp .env.example .env.local
```

Edit `.env.local` with your backend API endpoints:
```env
NEXT_PUBLIC_API_URL=http://localhost:8000/api/v1
NEXT_PUBLIC_AUTH_API_URL=http://localhost:8001/api/v1
NEXT_PUBLIC_TICKET_API_URL=http://localhost:8002/api/v1
NEXT_PUBLIC_CLIENT_API_URL=http://localhost:8003/api/v1
```

4. **Run development server**:
```bash
npm run dev
```

5. **Build for production**:
```bash
npm run build
npm start
```

## Project Structure

```
frontend/
├── app/                      # Next.js app directory
│   ├── auth/                # Authentication pages
│   │   └── login/          # Login page
│   ├── dashboard/          # Dashboard layout and pages
│   │   ├── layout.tsx      # Dashboard layout wrapper
│   │   └── page.tsx        # Main dashboard
│   ├── tickets/            # Ticket management
│   ├── customers/          # Customer management
│   ├── settings/           # Settings pages
│   ├── layout.tsx          # Root layout
│   ├── page.tsx           # Home page (redirects)
│   └── globals.css        # Global styles
├── components/
│   ├── layout/            # Layout components
│   │   └── sidebar.tsx    # Navigation sidebar
│   ├── ui/                # Reusable UI components
│   │   ├── button.tsx
│   │   ├── card.tsx
│   │   ├── input.tsx
│   │   ├── dialog.tsx
│   │   ├── table.tsx
│   │   └── ...more
│   └── providers.tsx      # App providers
├── lib/
│   ├── api.ts            # API client configuration
│   ├── auth.ts           # Auth store and utilities
│   └── utils.ts          # Utility functions
├── hooks/                # Custom React hooks
└── public/              # Static assets
```

## API Integration

The frontend is fully integrated with the backend microservices:

### Authentication Service
- Login/Logout
- Token refresh
- User profile management
- Password reset

### Ticket Service
- List, create, update, delete tickets
- Ticket assignment
- Comments and history
- Statistics and analytics

### Client Service
- Customer CRUD operations
- Customer notes
- Interaction history
- Customer merging

## Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `NEXT_PUBLIC_API_URL` | Main API gateway URL | `http://localhost:8000/api/v1` |
| `NEXT_PUBLIC_AUTH_API_URL` | Auth service URL | `http://localhost:8001/api/v1` |
| `NEXT_PUBLIC_TICKET_API_URL` | Ticket service URL | `http://localhost:8002/api/v1` |
| `NEXT_PUBLIC_CLIENT_API_URL` | Client service URL | `http://localhost:8003/api/v1` |
| `NEXT_PUBLIC_APP_NAME` | Application name | `AidlY` |
| `NEXT_PUBLIC_APP_URL` | Frontend URL | `http://localhost:3000` |

## License

MIT License
