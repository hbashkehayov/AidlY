import { NextRequest, NextResponse } from 'next/server';
import { cookies } from 'next/headers';

const AUTH_SERVICE_URL = process.env.AUTH_SERVICE_URL || 'http://localhost:8001';

export async function POST(request: NextRequest) {
  try {
    // Get the auth token from cookies or Authorization header
    const cookieStore = cookies();
    const token = cookieStore.get('auth_token')?.value ||
                  request.headers.get('authorization')?.replace('Bearer ', '');

    if (!token) {
      return NextResponse.json(
        { success: false, message: 'Unauthorized' },
        { status: 401 }
      );
    }

    // Get request body
    const body = await request.json();

    // Forward the request to the auth service
    console.log('Forwarding to auth service:', `${AUTH_SERVICE_URL}/api/v1/agents`);
    console.log('Token (first 20 chars):', token ? token.substring(0, 20) + '...' : 'NULL');

    const response = await fetch(`${AUTH_SERVICE_URL}/api/v1/agents`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`,
      },
      body: JSON.stringify(body),
    });

    console.log('Auth service response status:', response.status);

    const data = await response.json();
    console.log('Auth service response data:', data);

    // Return the response from auth service
    return NextResponse.json(data, { status: response.status });

  } catch (error) {
    console.error('API Error:', error);
    return NextResponse.json(
      { success: false, message: 'Internal server error' },
      { status: 500 }
    );
  }
}

export async function GET(request: NextRequest) {
  try {
    // Get the auth token from cookies or Authorization header
    const cookieStore = cookies();
    const token = cookieStore.get('auth_token')?.value ||
                  request.headers.get('authorization')?.replace('Bearer ', '');

    if (!token) {
      return NextResponse.json(
        { success: false, message: 'Unauthorized' },
        { status: 401 }
      );
    }

    // Forward the request to the auth service
    const response = await fetch(`${AUTH_SERVICE_URL}/api/v1/agents`, {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`,
      },
    });

    const data = await response.json();

    // Return the response from auth service
    return NextResponse.json(data, { status: response.status });

  } catch (error) {
    console.error('API Error:', error);
    return NextResponse.json(
      { success: false, message: 'Internal server error' },
      { status: 500 }
    );
  }
}