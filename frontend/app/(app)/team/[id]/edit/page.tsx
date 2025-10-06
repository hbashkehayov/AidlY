'use client';

import { useState, useEffect } from 'react';
import { useRouter, useParams } from 'next/navigation';
import { useQuery, useMutation } from '@tanstack/react-query';
import api from '@/lib/api';
import { ArrowLeft, Save, Loader2, Key } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { toast } from 'sonner';
import { Switch } from '@/components/ui/switch';

export default function EditTeamMemberPage() {
  const router = useRouter();
  const params = useParams();
  const userId = params.id as string;

  // Form state
  const [formData, setFormData] = useState({
    name: '',
    email: '',
    role: 'agent',
    is_active: true,
    password: '',
    password_confirmation: '',
  });

  // Fetch user data
  const { data: userData, isLoading: isLoadingUser } = useQuery({
    queryKey: ['user', userId],
    queryFn: async () => {
      const response = await api.users.get(userId);
      return response.data.data;
    },
  });


  // Update mutation
  const updateMutation = useMutation({
    mutationFn: async (data: any) => {
      const response = await api.users.update(userId, data);
      return response.data;
    },
    onSuccess: () => {
      toast.success('Team member updated successfully');
      router.push('/team');
    },
    onError: (error: any) => {
      toast.error(error.response?.data?.message || 'Failed to update team member');
    },
  });

  // Populate form when user data loads
  useEffect(() => {
    if (userData) {
      setFormData({
        name: userData.name || '',
        email: userData.email || '',
        role: userData.role || 'agent',
        is_active: userData.is_active ?? true,
        password: '', // Don't populate password
        password_confirmation: '',
      });
    }
  }, [userData]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    // Validation
    if (!formData.name || !formData.email) {
      toast.error('Name and email are required');
      return;
    }

    // Password validation (only if password is being changed)
    if (formData.password || formData.password_confirmation) {
      if (formData.password !== formData.password_confirmation) {
        toast.error('Passwords do not match');
        return;
      }
      if (formData.password.length < 8) {
        toast.error('Password must be at least 8 characters long');
        return;
      }
    }

    // Prepare data to send (exclude password fields if empty)
    const dataToSend: any = {
      name: formData.name,
      email: formData.email,
      role: formData.role,
      is_active: formData.is_active,
    };

    // Only include password if it's being changed
    if (formData.password) {
      dataToSend.password = formData.password;
    }

    updateMutation.mutate(dataToSend);
  };

  const handleInputChange = (field: string, value: any) => {
    setFormData(prev => ({
      ...prev,
      [field]: value,
    }));
  };

  if (isLoadingUser) {
    return (
      <div className="flex-1 flex items-center justify-center p-8">
        <div className="flex items-center gap-2">
          <Loader2 className="h-6 w-6 animate-spin" />
          <p>Loading user data...</p>
        </div>
      </div>
    );
  }

  if (!userData) {
    return (
      <div className="flex-1 flex items-center justify-center p-8">
        <div className="text-center">
          <p className="text-lg font-semibold">User not found</p>
          <Button onClick={() => router.push('/team')} className="mt-4">
            Back to Team
          </Button>
        </div>
      </div>
    );
  }

  return (
    <div className="flex-1 space-y-4 p-8 pt-6">
      {/* Header */}
      <div className="flex items-center gap-4">
        <Button
          variant="ghost"
          size="sm"
          onClick={() => router.push('/team')}
          className="gap-2"
        >
          <ArrowLeft className="h-4 w-4" />
          Back to Team
        </Button>
        <div>
          <h2 className="text-3xl font-bold tracking-tight">Edit Team Member</h2>
          <p className="text-muted-foreground">Update team member information</p>
        </div>
      </div>

      {/* Edit Form */}
      <form onSubmit={handleSubmit}>
        <div className="grid gap-6">
          {/* Main Card */}
          <Card>
            <CardHeader>
              <CardTitle>Team Member Information</CardTitle>
              <CardDescription>Update the team member details and role</CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              {/* Two column layout for form fields */}
              <div className="grid gap-6 md:grid-cols-2">
                <div className="space-y-2">
                  <Label htmlFor="name">Full Name</Label>
                  <Input
                    id="name"
                    value={formData.name}
                    onChange={(e) => handleInputChange('name', e.target.value)}
                    placeholder="Enter full name"
                    required
                  />
                </div>

                <div className="space-y-2">
                  <Label htmlFor="email">Email Address</Label>
                  <Input
                    id="email"
                    type="email"
                    value={formData.email}
                    onChange={(e) => handleInputChange('email', e.target.value)}
                    placeholder="Enter email address"
                    required
                  />
                </div>

                <div className="space-y-2">
                  <Label htmlFor="role">Role</Label>
                  <Select
                    value={formData.role}
                    onValueChange={(value) => handleInputChange('role', value)}
                  >
                    <SelectTrigger id="role">
                      <SelectValue placeholder="Select role" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="agent">Agent</SelectItem>
                      <SelectItem value="admin">Administrator</SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                <div className="space-y-2">
                  <Label htmlFor="is_active">Account Status</Label>
                  <div className="flex items-center h-10 px-3 border rounded-md">
                    <div className="flex items-center justify-between w-full">
                      <span className="text-sm">
                        {formData.is_active ? 'Active' : 'Inactive'}
                      </span>
                      <Switch
                        id="is_active"
                        checked={formData.is_active}
                        onCheckedChange={(checked) => handleInputChange('is_active', checked)}
                      />
                    </div>
                  </div>
                  <p className="text-xs text-muted-foreground">
                    Inactive users cannot log in to the system
                  </p>
                </div>
              </div>

              {/* Password Change Section */}
              <div className="border-t pt-6">
                <div className="flex items-center gap-2 mb-4">
                  <Key className="h-4 w-4 text-muted-foreground" />
                  <h3 className="text-sm font-medium">Change Password</h3>
                  <span className="text-xs text-muted-foreground">(Optional)</span>
                </div>
                <div className="grid gap-6 md:grid-cols-2">
                  <div className="space-y-2">
                    <Label htmlFor="password">New Password</Label>
                    <Input
                      id="password"
                      type="password"
                      value={formData.password}
                      onChange={(e) => handleInputChange('password', e.target.value)}
                      placeholder="Enter new password (min. 8 characters)"
                    />
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="password_confirmation">Confirm Password</Label>
                    <Input
                      id="password_confirmation"
                      type="password"
                      value={formData.password_confirmation}
                      onChange={(e) => handleInputChange('password_confirmation', e.target.value)}
                      placeholder="Confirm new password"
                    />
                  </div>
                </div>
                <p className="text-xs text-muted-foreground mt-2">
                  Leave blank to keep the current password unchanged
                </p>
              </div>

              {/* Actions */}
              <div className="flex justify-end gap-4 pt-4 border-t">
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => router.push('/team')}
                  disabled={updateMutation.isPending}
                >
                  Cancel
                </Button>
                <Button
                  type="submit"
                  disabled={updateMutation.isPending}
                  className="gap-2"
                >
                  {updateMutation.isPending ? (
                    <>
                      <Loader2 className="h-4 w-4 animate-spin" />
                      Saving...
                    </>
                  ) : (
                    <>
                      <Save className="h-4 w-4" />
                      Save Changes
                    </>
                  )}
                </Button>
              </div>
            </CardContent>
          </Card>
        </div>
      </form>
    </div>
  );
}
