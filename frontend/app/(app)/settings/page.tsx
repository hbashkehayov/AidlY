'use client';

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Separator } from '@/components/ui/separator';
import { useTheme } from 'next-themes';
import { useAuth } from '@/lib/auth';
import { useState, useEffect } from 'react';
import { toast } from 'sonner';
import { useSearchParams } from 'next/navigation';
import api from '@/lib/api';
import {
  Eye,
  EyeOff,
  Moon,
  Sun,
  Monitor,
  Volume2,
  VolumeX,
  Play,
} from 'lucide-react';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  NOTIFICATION_SOUNDS,
  playNotificationPreview,
  getSelectedNotificationSound,
  saveNotificationSound,
} from '@/lib/notification-sounds';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { cn } from '@/lib/utils';

export default function SettingsPage() {
  const { theme, setTheme } = useTheme();
  const { user } = useAuth();
  const searchParams = useSearchParams();

  // Get tab from URL parameter, default to 'profile'
  const [activeTab, setActiveTab] = useState('profile');

  // Font size state
  const [fontSize, setFontSize] = useState('medium');

  // Notification sound state
  const [notificationSound, setNotificationSound] = useState(true);
  const [selectedSoundType, setSelectedSoundType] = useState('ding');

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

  // Load notification sound preference on mount
  useEffect(() => {
    if (user?.id) {
      const savedSoundPref = localStorage.getItem(`notifications_sound_${user.id}`);
      const soundEnabled = savedSoundPref !== 'false'; // Default to true
      setNotificationSound(soundEnabled);

      const savedSoundType = getSelectedNotificationSound(user.id);
      setSelectedSoundType(savedSoundType);
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
  };

  // Handle notification sound toggle
  const handleNotificationSoundToggle = (enabled: boolean) => {
    if (!user?.id) {
      toast.error('Please log in to save preferences');
      return;
    }

    console.log('Settings page - Toggling notification sound to:', enabled, 'for user:', user.id);
    setNotificationSound(enabled);
    localStorage.setItem(`notifications_sound_${user.id}`, String(enabled));

    // Play a test sound when enabling
    if (enabled) {
      playNotificationPreview(selectedSoundType);
      toast.success('Notification sounds enabled');
    } else {
      toast.success('Notification sounds disabled');
    }
  };

  // Handle sound type change
  const handleSoundTypeChange = (soundId: string) => {
    if (!user?.id) {
      toast.error('Please log in to save preferences');
      return;
    }

    console.log('Settings page - Changing notification sound to:', soundId, 'for user:', user.id);
    setSelectedSoundType(soundId);
    saveNotificationSound(user.id, soundId);

    const soundName = NOTIFICATION_SOUNDS.find(s => s.id === soundId)?.name || soundId;
    toast.success(`Notification sound changed to ${soundName}`);
  };

  // Handle sound preview
  const handlePlayPreview = (soundId: string, event: React.MouseEvent) => {
    event.stopPropagation();
    playNotificationPreview(soundId);
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
          <TabsTrigger value="notifications">Notifications</TabsTrigger>
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

        <TabsContent value="notifications" className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>Notification Preferences</CardTitle>
              <CardDescription>
                Manage how you receive notifications
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              <div className="flex items-center justify-between">
                <div className="space-y-0.5">
                  <div className="flex items-center gap-2">
                    {notificationSound ? (
                      <Volume2 className="h-4 w-4 text-muted-foreground" />
                    ) : (
                      <VolumeX className="h-4 w-4 text-muted-foreground" />
                    )}
                    <Label htmlFor="notification-sound" className="text-base font-medium cursor-pointer">
                      Sound Notifications
                    </Label>
                  </div>
                  <p className="text-sm text-muted-foreground">
                    Play a sound when you receive new notifications
                  </p>
                </div>
                <Switch
                  id="notification-sound"
                  checked={notificationSound}
                  onCheckedChange={handleNotificationSoundToggle}
                />
              </div>

              {notificationSound && (
                <>
                  <Separator />
                  <div className="space-y-3">
                    <Label className="text-base font-medium">Notification Sound</Label>
                    <p className="text-sm text-muted-foreground">
                      Choose which sound to play for notifications
                    </p>
                    <RadioGroup value={selectedSoundType} onValueChange={handleSoundTypeChange}>
                      <div className="space-y-2">
                        {NOTIFICATION_SOUNDS.map((sound) => (
                          <div
                            key={sound.id}
                            className={cn(
                              "flex items-center justify-between space-x-2 rounded-lg border p-3 transition-colors",
                              selectedSoundType === sound.id
                                ? "border-primary bg-primary/5"
                                : "border-border hover:bg-accent"
                            )}
                          >
                            <div className="flex items-center space-x-3 flex-1">
                              <RadioGroupItem value={sound.id} id={sound.id} />
                              <Label
                                htmlFor={sound.id}
                                className="flex-1 cursor-pointer space-y-0.5"
                              >
                                <div className="font-medium">{sound.name}</div>
                                <div className="text-xs text-muted-foreground">
                                  {sound.description}
                                </div>
                              </Label>
                            </div>
                            <Button
                              type="button"
                              variant="ghost"
                              size="sm"
                              className="h-8 w-8 p-0"
                              onClick={(e) => handlePlayPreview(sound.id, e)}
                              title="Play preview"
                            >
                              <Play className="h-4 w-4" />
                            </Button>
                          </div>
                        ))}
                      </div>
                    </RadioGroup>
                  </div>
                </>
              )}

              <Separator />
              <div className="rounded-lg bg-muted p-4">
                <p className="text-sm text-muted-foreground">
                  <strong>Note:</strong> Sound notifications will only play when new notifications arrive.
                  The bell icon updates every 3 seconds to check for new notifications.
                </p>
              </div>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  );
}
