'use client';

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import { useTheme } from 'next-themes';
import { useAuth } from '@/lib/auth';
import { useState, useEffect } from 'react';
import { toast } from 'sonner';
import { useSearchParams } from 'next/navigation';
import api from '@/lib/api';
import {
  Users,
  Eye,
  EyeOff,
  Moon,
  Sun,
  Monitor,
} from 'lucide-react';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';

export default function SettingsPage() {
  const { theme, setTheme } = useTheme();
  const { user, token } = useAuth();
  const searchParams = useSearchParams();

  // Get tab from URL parameter, default to 'profile'
  const [activeTab, setActiveTab] = useState('profile');

  // Font size state
  const [fontSize, setFontSize] = useState('medium');

  useEffect(() => {
    const tabParam = searchParams.get('tab');
    if (tabParam) {
      setActiveTab(tabParam);
    }
  }, [searchParams]);

  // Load font size from localStorage on mount
  useEffect(() => {
    if (user?.id) {
      const savedFontSize = localStorage.getItem(`fontSize_${user.id}`) || 'medium';
      console.log('Settings page - Loading saved font size for user:', user.id, savedFontSize);
      setFontSize(savedFontSize);
      document.documentElement.setAttribute('data-font-size', savedFontSize);
    }
  }, [user?.id]);

  // Load theme preference on mount
  useEffect(() => {
    if (user?.id) {
      const savedTheme = localStorage.getItem(`theme_${user.id}`);
      if (savedTheme) {
        console.log('Settings page - Loading saved theme for user:', user.id, savedTheme);
        setTheme(savedTheme);
      }
    }
  }, [user?.id, setTheme]);

  // Handle font size change
  const handleFontSizeChange = (size: string) => {
    if (!user?.id) {
      toast.error('Please log in to save preferences');
      return;
    }

    console.log('Settings page - Changing font size to:', size, 'for user:', user.id);
    setFontSize(size);
    localStorage.setItem(`fontSize_${user.id}`, size);
    document.documentElement.setAttribute('data-font-size', size);

    // Verify it was saved
    const verify = localStorage.getItem(`fontSize_${user.id}`);
    console.log('Settings page - Verified saved font size:', verify);

    toast.success(`Font size changed to ${size.charAt(0).toUpperCase() + size.slice(1)}`);
  };

  // Handle theme change with feedback
  const handleThemeChange = (newTheme: string) => {
    if (!user?.id) {
      toast.error('Please log in to save preferences');
      return;
    }

    console.log('Settings page - Changing theme to:', newTheme, 'for user:', user.id);
    setTheme(newTheme);
    localStorage.setItem(`theme_${user.id}`, newTheme);

    const themeName = newTheme.charAt(0).toUpperCase() + newTheme.slice(1);
    toast.success(`Theme changed to ${themeName}`);
  };

  // Profile update state
  const [profileLoading, setProfileLoading] = useState(false);
  const [profileData, setProfileData] = useState({
    name: user?.name || '',
  });

  // Password change state
  const [passwordLoading, setPasswordLoading] = useState(false);
  const [showPasswords, setShowPasswords] = useState({
    current: false,
    new: false,
    confirm: false,
  });
  const [passwordData, setPasswordData] = useState({
    currentPassword: '',
    newPassword: '',
    confirmPassword: '',
  });

  // User creation form state (for admin)
  const [isLoading, setIsLoading] = useState(false);
  const [showPassword, setShowPassword] = useState(false);
  const [formData, setFormData] = useState({
    name: '',
    email: '',
    password: '',
    confirmPassword: '',
    role: 'agent',
    enableEmailIntegration: false,
    gmailAddress: '',
    gmailAppPassword: '',
    agentSignature: ''
  });

  // Update profile data when user changes
  useEffect(() => {
    if (user) {
      setProfileData({
        name: user.name || '',
      });
    }
  }, [user]);

  const handleProfileUpdate = async () => {
    if (!profileData.name.trim()) {
      toast.error('Full name is required');
      return;
    }

    setProfileLoading(true);
    try {
      const response = await api.auth.updateProfile({
        name: profileData.name,
      });

      if (response.data.success) {
        toast.success('Profile updated successfully');

        // Update user in localStorage
        const storedUser = JSON.parse(localStorage.getItem('user') || '{}');
        storedUser.name = profileData.name;
        localStorage.setItem('user', JSON.stringify(storedUser));

        // Trigger a page refresh to update the UI
        window.location.reload();
      } else {
        toast.error(response.data.message || 'Failed to update profile');
      }
    } catch (error: any) {
      console.error('Profile update error:', error);
      toast.error(error.response?.data?.message || 'Failed to update profile');
    } finally {
      setProfileLoading(false);
    }
  };

  const handlePasswordChange = async () => {
    if (!passwordData.currentPassword || !passwordData.newPassword || !passwordData.confirmPassword) {
      toast.error('All password fields are required');
      return;
    }

    if (passwordData.newPassword !== passwordData.confirmPassword) {
      toast.error('New passwords do not match');
      return;
    }

    if (passwordData.newPassword.length < 8) {
      toast.error('New password must be at least 8 characters long');
      return;
    }

    if (passwordData.newPassword === passwordData.currentPassword) {
      toast.error('New password must be different from current password');
      return;
    }

    setPasswordLoading(true);
    try {
      const response = await api.auth.changePassword({
        current_password: passwordData.currentPassword,
        new_password: passwordData.newPassword,
        new_password_confirmation: passwordData.confirmPassword,
      });

      if (response.data.success) {
        toast.success('Password changed successfully');
        setPasswordData({
          currentPassword: '',
          newPassword: '',
          confirmPassword: '',
        });
      } else {
        toast.error(response.data.message || 'Failed to change password');
      }
    } catch (error: any) {
      console.error('Password change error:', error);
      const errorMessage = error.response?.data?.message || error.response?.data?.errors?.new_password?.[0] || 'Failed to change password';
      toast.error(errorMessage);
    } finally {
      setPasswordLoading(false);
    }
  };

  const handleInputChange = (field: string, value: string | boolean) => {
    setFormData(prev => ({ ...prev, [field]: value }));
  };

  const handleCreateUser = async () => {
    if (!formData.name || !formData.email || !formData.password) {
      toast.error('Please fill in all required fields');
      return;
    }

    if (formData.password !== formData.confirmPassword) {
      toast.error('Passwords do not match');
      return;
    }

    if (formData.enableEmailIntegration && (!formData.gmailAddress || !formData.gmailAppPassword)) {
      toast.error('Gmail address and app password are required for email integration');
      return;
    }

    setIsLoading(true);

    if (!token) {
      toast.error('Authentication token not found. Please log in again.');
      setIsLoading(false);
      return;
    }

    try {
      const response = await fetch('/api/agents', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({
          name: formData.name,
          email: formData.email,
          password: formData.password,
          password_confirmation: formData.confirmPassword,
          role: formData.role,
          enable_email_integration: formData.enableEmailIntegration,
          gmail_address: formData.gmailAddress,
          gmail_app_password: formData.gmailAppPassword,
          agent_signature: formData.agentSignature || `Best regards,\n${formData.name}\nAidlY Support Team`
        })
      });

      const data = await response.json();

      if (data.success) {
        toast.success('User created successfully!');
        setFormData({
          name: '',
          email: '',
          password: '',
          confirmPassword: '',
          role: 'agent',
          enableEmailIntegration: false,
          gmailAddress: '',
          gmailAppPassword: '',
          agentSignature: ''
        });
      } else {
        if (data.message === 'Invalid or expired token') {
          toast.error('Your session has expired. Please log in again.');
        } else {
          toast.error(data.message || 'Failed to create user');
        }
      }
    } catch (error) {
      console.error('User creation error:', error);
      toast.error('Network error. Please try again.');
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="flex-1 space-y-4 p-8 pt-6">
      <div>
        <h2 className="text-3xl font-bold tracking-tight">Settings</h2>
        <p className="text-muted-foreground">
          Manage your account settings and preferences
        </p>
      </div>

      <Tabs value={activeTab} onValueChange={setActiveTab} className="space-y-4">
        <TabsList>
          <TabsTrigger value="profile">Profile</TabsTrigger>
          <TabsTrigger value="appearance">Appearance</TabsTrigger>
          {user?.role === 'admin' && (
            <TabsTrigger value="users">User Management</TabsTrigger>
          )}
        </TabsList>

        <TabsContent value="profile" className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>Profile Information</CardTitle>
              <CardDescription>
                Update your personal information
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="space-y-2">
                <Label htmlFor="name">Full Name</Label>
                <Input
                  id="name"
                  placeholder="John Doe"
                  value={profileData.name}
                  onChange={(e) => setProfileData({ ...profileData, name: e.target.value })}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="email">Email</Label>
                <Input id="email" type="email" value={user?.email} disabled />
                <p className="text-xs text-muted-foreground">Email cannot be changed</p>
              </div>
              <div className="pt-4">
                <Button onClick={handleProfileUpdate} disabled={profileLoading}>
                  {profileLoading ? 'Updating...' : 'Update Profile'}
                </Button>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Change Password</CardTitle>
              <CardDescription>
                Update your password to keep your account secure
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="space-y-2">
                <Label htmlFor="currentPassword">Current Password</Label>
                <div className="relative">
                  <Input
                    id="currentPassword"
                    type={showPasswords.current ? 'text' : 'password'}
                    value={passwordData.currentPassword}
                    onChange={(e) => setPasswordData({ ...passwordData, currentPassword: e.target.value })}
                  />
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="absolute right-0 top-0 h-full px-3"
                    onClick={() => setShowPasswords({ ...showPasswords, current: !showPasswords.current })}
                  >
                    {showPasswords.current ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                  </Button>
                </div>
              </div>
              <div className="space-y-2">
                <Label htmlFor="newPassword">New Password</Label>
                <div className="relative">
                  <Input
                    id="newPassword"
                    type={showPasswords.new ? 'text' : 'password'}
                    value={passwordData.newPassword}
                    onChange={(e) => setPasswordData({ ...passwordData, newPassword: e.target.value })}
                  />
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="absolute right-0 top-0 h-full px-3"
                    onClick={() => setShowPasswords({ ...showPasswords, new: !showPasswords.new })}
                  >
                    {showPasswords.new ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                  </Button>
                </div>
                <p className="text-xs text-muted-foreground">Must be at least 8 characters</p>
              </div>
              <div className="space-y-2">
                <Label htmlFor="confirmPassword">Confirm New Password</Label>
                <div className="relative">
                  <Input
                    id="confirmPassword"
                    type={showPasswords.confirm ? 'text' : 'password'}
                    value={passwordData.confirmPassword}
                    onChange={(e) => setPasswordData({ ...passwordData, confirmPassword: e.target.value })}
                  />
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="absolute right-0 top-0 h-full px-3"
                    onClick={() => setShowPasswords({ ...showPasswords, confirm: !showPasswords.confirm })}
                  >
                    {showPasswords.confirm ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                  </Button>
                </div>
              </div>
              <div className="pt-4">
                <Button onClick={handlePasswordChange} disabled={passwordLoading}>
                  {passwordLoading ? 'Changing Password...' : 'Change Password'}
                </Button>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="appearance" className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>Theme</CardTitle>
              <CardDescription>
                Customize the appearance of your dashboard
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="space-y-2">
                <Label>Color Theme</Label>
                <div className="grid grid-cols-3 gap-2">
                  <Button
                    variant={theme === 'light' ? 'default' : 'outline'}
                    className="justify-start"
                    onClick={() => handleThemeChange('light')}
                  >
                    <Sun className="mr-2 h-4 w-4" />
                    Light
                  </Button>
                  <Button
                    variant={theme === 'dark' ? 'default' : 'outline'}
                    className="justify-start"
                    onClick={() => handleThemeChange('dark')}
                  >
                    <Moon className="mr-2 h-4 w-4" />
                    Dark
                  </Button>
                  <Button
                    variant={theme === 'system' ? 'default' : 'outline'}
                    className="justify-start"
                    onClick={() => handleThemeChange('system')}
                  >
                    <Monitor className="mr-2 h-4 w-4" />
                    System
                  </Button>
                </div>
                <p className="text-xs text-muted-foreground">
                  Your theme preference is automatically saved
                </p>
              </div>
              <Separator />
              <div className="space-y-2">
                <Label htmlFor="fontSize">Font Size</Label>
                <Select value={fontSize} onValueChange={handleFontSizeChange}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="small">Small</SelectItem>
                    <SelectItem value="medium">Medium (Default)</SelectItem>
                    <SelectItem value="large">Large</SelectItem>
                  </SelectContent>
                </Select>
                <p className="text-xs text-muted-foreground">
                  Font size is automatically saved and applied across the application
                </p>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        {user?.role === 'admin' && (
          <TabsContent value="users" className="space-y-4">
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <Users className="h-5 w-5" />
                  Create New User
                </CardTitle>
                <CardDescription>
                  Add a new agent to the platform with optional email integration
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-6">
                <div className="grid grid-cols-2 gap-4">
                  <div className="space-y-2">
                    <Label htmlFor="name">Full Name *</Label>
                    <Input
                      id="name"
                      placeholder="John Doe"
                      value={formData.name}
                      onChange={(e) => handleInputChange('name', e.target.value)}
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="email">Email Address *</Label>
                    <Input
                      id="email"
                      type="email"
                      placeholder="john@company.com"
                      value={formData.email}
                      onChange={(e) => handleInputChange('email', e.target.value)}
                    />
                  </div>
                </div>

                <div className="grid grid-cols-2 gap-4">
                  <div className="space-y-2">
                    <Label htmlFor="password">Password *</Label>
                    <div className="relative">
                      <Input
                        id="password"
                        type={showPassword ? 'text' : 'password'}
                        placeholder="Enter password"
                        value={formData.password}
                        onChange={(e) => handleInputChange('password', e.target.value)}
                      />
                      <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="absolute right-0 top-0 h-full px-3"
                        onClick={() => setShowPassword(!showPassword)}
                      >
                        {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                      </Button>
                    </div>
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="confirmPassword">Confirm Password *</Label>
                    <Input
                      id="confirmPassword"
                      type="password"
                      placeholder="Confirm password"
                      value={formData.confirmPassword}
                      onChange={(e) => handleInputChange('confirmPassword', e.target.value)}
                    />
                  </div>
                </div>

                <div className="space-y-2">
                  <Label htmlFor="role">Role</Label>
                  <Select
                    value={formData.role}
                    onValueChange={(value) => handleInputChange('role', value)}
                  >
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="agent">Agent</SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                <Separator />

                <div className="space-y-4">
                  <div className="flex items-center justify-between">
                    <div className="space-y-1">
                      <p className="text-sm font-medium">Enable Email Integration</p>
                      <p className="text-xs text-muted-foreground">
                        Allow this user to fetch emails and send replies through their Gmail account
                      </p>
                    </div>
                    <Switch
                      checked={formData.enableEmailIntegration}
                      onCheckedChange={(checked) => handleInputChange('enableEmailIntegration', checked)}
                    />
                  </div>

                  {formData.enableEmailIntegration && (
                    <div className="space-y-4 rounded-lg border p-4 bg-muted/50">
                      <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-2">
                          <Label htmlFor="gmailAddress">Gmail Address *</Label>
                          <Input
                            id="gmailAddress"
                            type="email"
                            placeholder="john.agent@gmail.com"
                            value={formData.gmailAddress}
                            onChange={(e) => handleInputChange('gmailAddress', e.target.value)}
                          />
                        </div>
                        <div className="space-y-2">
                          <Label htmlFor="gmailAppPassword">Gmail App Password *</Label>
                          <Input
                            id="gmailAppPassword"
                            type="password"
                            placeholder="xxxx xxxx xxxx xxxx"
                            value={formData.gmailAppPassword}
                            onChange={(e) => handleInputChange('gmailAppPassword', e.target.value)}
                          />
                          <p className="text-xs text-muted-foreground">
                            Generate an app password in your Gmail security settings
                          </p>
                        </div>
                      </div>

                      <div className="space-y-2">
                        <Label htmlFor="agentSignature">Email Signature (Optional)</Label>
                        <Textarea
                          id="agentSignature"
                          placeholder={`Best regards,\n${formData.name}\nAidlY Support Team`}
                          rows={4}
                          value={formData.agentSignature}
                          onChange={(e) => handleInputChange('agentSignature', e.target.value)}
                        />
                      </div>

                      <div className="rounded-lg bg-blue-50 p-3 text-sm">
                        <p className="font-medium text-blue-900">Email Integration Benefits:</p>
                        <ul className="mt-1 space-y-1 text-blue-800">
                          <li>• Automatic email fetching every 5 minutes</li>
                          <li>• Replies sent from agent's own Gmail address</li>
                          <li>• Professional email threading and formatting</li>
                          <li>• Centralized ticket management</li>
                        </ul>
                      </div>
                    </div>
                  )}
                </div>

                <div className="pt-4">
                  <Button
                    onClick={handleCreateUser}
                    disabled={isLoading}
                    className="w-full"
                  >
                    {isLoading ? 'Creating User...' : 'Create User'}
                  </Button>
                </div>
              </CardContent>
            </Card>
          </TabsContent>
        )}
      </Tabs>
    </div>
  );
}
