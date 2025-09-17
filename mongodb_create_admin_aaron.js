// MongoDB Script to Create Admin User "aaron"
// Execute this script in MongoDB Shell or MongoDB Compass
// Database: bigplantracker (or your database name)

// ============================================
// 🚀 BIG PLAN TRACKER - ADMIN USER CREATION
// ============================================

print("🚀 Starting admin user 'aaron' creation process...");
print("📅 Date: " + new Date().toISOString());
print("==================================================");

// Switch to the correct database
db = db.getSiblingDB('bigplantracker');

// ============================================
// 1. CREATE COMPANY FOR ADMIN USER
// ============================================

print("\n📋 Step 1: Creating Global Admin Company...");

// Check if company already exists
var existingCompany = db.companies.findOne({name: "Global Admin Company"});

var companyId;
if (existingCompany) {
    print("✅ Company already exists, using existing ID");
    companyId = existingCompany._id;
} else {
    var companyResult = db.companies.insertOne({
        name: "Global Admin Company",
        description: "Company for global administrators and system management",
        created_at: new Date(),
        updated_at: new Date(),
        is_active: true,
        settings: {
            timezone: "UTC",
            currency: "USD",
            language: "en"
        },
        contact_info: {
            email: "admin@bigplantracker.com",
            phone: "+1-000-000-0000"
        }
    });
    
    companyId = companyResult.insertedId;
    print("✅ Company created with ID: " + companyId);
}

// ============================================
// 2. CREATE ADMIN USER "AARON"
// ============================================

print("\n👤 Step 2: Creating admin user 'aaron'...");

// Check if user already exists
var existingUser = db.users.findOne({username: "aaron"});

if (existingUser) {
    print("⚠️  User 'aaron' already exists!");
    print("📧 Email: " + existingUser.email);
    print("🏢 Company: " + existingUser.company_id);
    print("🔐 To reset password, delete the user first or update manually");
} else {
    // Password hash for "Redrover99!@" (bcrypt)
    var hashedPassword = "$2y$12$gOoc.H07VTwpoU6USsxlPelAgE5D3ZnS2R0QzoVIPyNM4GcV2EUnS";
    
    var userResult = db.users.insertOne({
        username: "aaron",
        email: "aaron@admin.com",
        password: hashedPassword,
        first_name: "Aaron",
        last_name: "Administrator",
        role: "admin",
        company_id: companyId,
        status: "active",
        is_active: true,
        permissions: [
            "users.manage",
            "companies.manage", 
            "projects.manage",
            "tasks.manage",
            "reports.view",
            "system.admin",
            "global.admin"
        ],
        profile: {
            avatar: null,
            bio: "Global system administrator",
            phone: null,
            department: "IT Administration"
        },
        preferences: {
            theme: "light",
            language: "en",
            timezone: "UTC",
            notifications: {
                email: true,
                push: true,
                sms: false
            }
        },
        security: {
            two_factor_enabled: false,
            last_password_change: new Date(),
            failed_login_attempts: 0,
            account_locked: false
        },
        created_at: new Date(),
        updated_at: new Date(),
        last_login: null,
        created_by: "system_script",
        notes: "Global administrator created via MongoDB script"
    });
    
    print("✅ User 'aaron' created successfully!");
    print("🆔 User ID: " + userResult.insertedId);
}

// ============================================
// 3. CREATE SYSTEM ACTIVITY LOG
// ============================================

print("\n📝 Step 3: Logging system activity...");

db.system_activities.insertOne({
    activity_type: "admin_user_created",
    description: "Global admin user 'aaron' created via MongoDB script",
    user_id: existingUser ? existingUser._id : userResult.insertedId,
    company_id: companyId,
    metadata: {
        script_version: "1.0",
        execution_date: new Date(),
        permissions_granted: [
            "users.manage",
            "companies.manage", 
            "projects.manage",
            "tasks.manage",
            "reports.view",
            "system.admin",
            "global.admin"
        ]
    },
    created_at: new Date(),
    ip_address: "system_script",
    user_agent: "MongoDB Script"
});

print("✅ Activity logged successfully");

// ============================================
// 4. VERIFICATION
// ============================================

print("\n🔍 Step 4: Verifying user creation...");

var createdUser = db.users.findOne({username: "aaron"});
var userCompany = db.companies.findOne({_id: createdUser.company_id});

if (createdUser) {
    print("✅ Verification successful!");
    print("\n==================================================");
    print("🎉 ADMIN USER CREATED SUCCESSFULLY!");
    print("==================================================");
    print("👤 Username: " + createdUser.username);
    print("📧 Email: " + createdUser.email);
    print("🔐 Password: Redrover99!@");
    print("🏢 Company: " + userCompany.name);
    print("👑 Role: " + createdUser.role);
    print("📅 Created: " + createdUser.created_at.toISOString());
    print("🔑 Permissions: " + createdUser.permissions.length + " permissions granted");
    print("\n📋 Full Permissions List:");
    createdUser.permissions.forEach(function(perm) {
        print("   ✓ " + perm);
    });
    print("\n==================================================");
    print("🚀 Ready to use! Login with the credentials above.");
    print("⚠️  IMPORTANT: Change the password after first login!");
    print("==================================================");
} else {
    print("❌ Verification failed - user not found!");
}

// ============================================
// 5. ADDITIONAL QUERIES FOR VERIFICATION
// ============================================

print("\n🔧 Additional verification queries:");
print("📊 Total users in system: " + db.users.countDocuments());
print("🏢 Total companies in system: " + db.companies.countDocuments());
print("👑 Total admin users: " + db.users.countDocuments({role: "admin"}));

// Show all admin users
print("\n👑 All admin users in system:");
db.users.find({role: "admin"}, {username: 1, email: 1, first_name: 1, last_name: 1}).forEach(function(user) {
    print("   • " + user.username + " (" + user.first_name + " " + user.last_name + ") - " + user.email);
});

print("\n✨ Script execution completed!");
print("📝 Log: Check system_activities collection for execution details");